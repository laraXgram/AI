<?php

namespace LaraGram\Ai\Telegram;

use Closure;
use LaraGram\Ai\Approvals\Decision;
use LaraGram\Ai\Approvals\Decisions;
use LaraGram\Ai\Approvals\PendingApproval;
use LaraGram\Ai\Contracts\Agent;
use LaraGram\Ai\Events\ToolApprovalRequested;
use LaraGram\Ai\Responses\AgentResponse;
use LaraGram\Ai\Telegram\Formatting\TelegramHtml;
use LaraGram\Request\Request;
use LaraGram\Support\Collection;
use LaraGram\Support\Facades\Cache;
use LaraGram\Support\Str;
use LogicException;
use Throwable;

class Approvals
{
    /**
     * The callback data prefix of the approval buttons.
     */
    public const PREFIX = 'lgai';

    /**
     * The agents that paused for approval in this process, keyed by invocation.
     *
     * @var array<string, \LaraGram\Ai\Contracts\Agent>
     */
    protected static array $agents = [];

    /**
     * The callback that renders the approval message.
     *
     * @var (\Closure(\LaraGram\Support\Collection<int, \LaraGram\Ai\Approvals\PendingApproval>, array<string, bool>): string)|null
     */
    protected static ?Closure $messageCallback = null;

    /**
     * The callback that determines who may decide an approval.
     *
     * @var (\Closure(object, array): bool)|null
     */
    protected static ?Closure $authorizeCallback = null;

    /**
     * Remember the agent that paused, so the run can be resumed from Telegram.
     */
    public static function remember(ToolApprovalRequested $event): void
    {
        static::$agents[$event->invocationId] = $event->agent;

        if (count(static::$agents) > 50) {
            static::$agents = array_slice(static::$agents, -50, preserve_keys: true);
        }
    }

    /**
     * Ask the chat to approve or reject the pending tool calls of the response.
     */
    public static function request(AgentResponse $response, Chat $chat): void
    {
        $agent = static::$agents[$response->invocationId] ?? null;

        if (! $agent instanceof Agent || $response->conversationId === null || ! method_exists($agent, 'continue')) {
            throw new LogicException(
                'Approving tools from Telegram requires an agent that remembers conversations, such as one using the RemembersConversations trait.'
            );
        }

        $approvals = $response->pendingApprovals->values();
        $token = Str::random(16);

        $state = [
            'agent' => $agent,
            'conversation' => $response->conversationId,
            'participant' => $response->conversationUser,
            'chat' => $chat->withoutReply(),
            'user' => $chat->userId,
            'approvals' => $approvals->map->toArray()->all(),
            'decisions' => [],
            'message' => null,
        ];

        $sender = Sender::to($chat);

        $result = $sender->call('sendMessage', [
            ...$chat->parameters(),
            'text' => static::message($approvals, []),
            'parse_mode' => 'HTML',
            'reply_markup' => static::keyboard($token, $approvals, []),
        ]);

        $state['message'] = $result['message_id'] ?? null;

        Cache::put(static::key($token), $state, (int) config('ai.telegram.approvals.ttl', 86400));
    }

    /**
     * Record a decision made with one of the approval buttons, resuming the run once every call is decided.
     */
    public static function decide(object $request, string $token, string $call, string $decision): void
    {
        $state = Cache::get(static::key($token));
        $chat = Chat::fromRequest($request);
        $sender = $chat ? Sender::to($chat) : null;

        if ($state === null) {
            $sender?->answerCallback('This approval has expired.');

            return;
        }

        $user = $request instanceof Request ? Chat::userOf($request) : null;

        if (! static::authorized($user, $state)) {
            $sender?->answerCallback('Only the person who asked may decide.');

            return;
        }

        $approvals = collect($state['approvals'])->map(
            fn (array $approval) => new PendingApproval($approval['id'], $approval['tool'], $approval['arguments'] ?? [], $approval['reason'] ?? null)
        )->values();

        $approve = $decision === 'a';

        foreach ($approvals as $index => $approval) {
            if ($call === '*' || (string) $index === $call) {
                $state['decisions'][$approval->id] ??= $approve;
            }
        }

        $done = count($state['decisions']) === $approvals->count();

        $sender?->answerCallback($approve ? '✅' : '❌');

        if ($state['message'] !== null) {
            try {
                $state['chat']->messenger()->call('editMessageText', [
                    'chat_id' => $state['chat']->id,
                    'message_id' => $state['message'],
                    'text' => static::message($approvals, $state['decisions']),
                    'parse_mode' => 'HTML',
                    'reply_markup' => $done ? ['inline_keyboard' => []] : static::keyboard($token, $approvals, $state['decisions']),
                ]);
            } catch (Throwable) {
                //
            }
        }

        if (! $done) {
            Cache::put(static::key($token), $state, (int) config('ai.telegram.approvals.ttl', 86400));

            return;
        }

        Cache::forget(static::key($token));

        $decisions = Decisions::from(collect($state['decisions'])->map(
            fn (bool $approved) => $approved
                ? Decision::approve()
                : Decision::reject((string) config('ai.telegram.approvals.rejection', 'The user rejected this action.'))
        )->all());

        $state['agent']
            ->continue($state['conversation'], as: $state['participant'])
            ->stream($decisions)
            ->sendTo($state['chat']);
    }

    /**
     * Customize the message that asks for approval.
     *
     * @param  \Closure(\LaraGram\Support\Collection<int, \LaraGram\Ai\Approvals\PendingApproval>, array<string, bool>): string  $callback
     */
    public static function messageUsing(Closure $callback): void
    {
        static::$messageCallback = $callback;
    }

    /**
     * Customize who may approve or reject the pending tool calls.
     *
     * @param  \Closure(object|null, array): bool  $callback
     */
    public static function authorizeUsing(Closure $callback): void
    {
        static::$authorizeCallback = $callback;
    }

    /**
     * Build the approval message.
     *
     * @param  \LaraGram\Support\Collection<int, \LaraGram\Ai\Approvals\PendingApproval>  $approvals
     * @param  array<string, bool>  $decisions
     */
    protected static function message(Collection $approvals, array $decisions): string
    {
        if (static::$messageCallback) {
            return (static::$messageCallback)($approvals, $decisions);
        }

        $lines = ['🔐 <b>'.TelegramHtml::escape((string) config('ai.telegram.approvals.title', 'Approval needed')).'</b>'];

        foreach ($approvals as $index => $approval) {
            $status = match ($decisions[$approval->id] ?? null) {
                true => '✅',
                false => '❌',
                default => ($index + 1).'.',
            };

            $arguments = json_encode($approval->arguments, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            $lines[] = $status.' <b>'.TelegramHtml::escape($approval->tool).'</b>'
                .($approval->reason ? ' — '.TelegramHtml::escape($approval->reason) : '')
                ."\n<pre>".TelegramHtml::escape(Str::limit((string) $arguments, 600)).'</pre>';
        }

        return implode("\n\n", $lines);
    }

    /**
     * Build the keyboard of the undecided calls.
     *
     * @param  \LaraGram\Support\Collection<int, \LaraGram\Ai\Approvals\PendingApproval>  $approvals
     * @param  array<string, bool>  $decisions
     * @return array{inline_keyboard: array<int, array<int, array{text: string, callback_data: string}>>}
     */
    protected static function keyboard(string $token, Collection $approvals, array $decisions): array
    {
        $rows = [];
        $single = $approvals->count() === 1;

        foreach ($approvals as $index => $approval) {
            if (isset($decisions[$approval->id])) {
                continue;
            }

            $rows[] = [
                ['text' => $single ? '✅ Approve' : '✅ '.($index + 1), 'callback_data' => static::PREFIX.":{$token}:{$index}:a"],
                ['text' => $single ? '❌ Reject' : '❌ '.($index + 1), 'callback_data' => static::PREFIX.":{$token}:{$index}:r"],
            ];
        }

        if (! $single && count($rows) > 1) {
            $rows[] = [
                ['text' => '✅ Approve all', 'callback_data' => static::PREFIX.":{$token}:*:a"],
                ['text' => '❌ Reject all', 'callback_data' => static::PREFIX.":{$token}:*:r"],
            ];
        }

        return ['inline_keyboard' => $rows];
    }

    /**
     * Determine if the given Telegram user may decide the approval.
     */
    protected static function authorized(?object $user, array $state): bool
    {
        if (static::$authorizeCallback) {
            return (bool) (static::$authorizeCallback)($user, $state);
        }

        return $state['user'] === null || ($user !== null && (string) $user->id === (string) $state['user']);
    }

    /**
     * Get the cache key of an approval.
     */
    protected static function key(string $token): string
    {
        return 'laragram-ai:approval:'.$token;
    }
}
