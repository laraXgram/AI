<?php

namespace LaraGram\Ai\Telegram\Middleware;

use Closure;
use LaraGram\Ai\Classification;
use LaraGram\Ai\Classification\Boolean;
use LaraGram\Ai\Events\MessageFlagged;
use LaraGram\Ai\Telegram\Chat;
use LaraGram\Ai\Telegram\Sender;
use LaraGram\Request\Request;
use LaraGram\Support\Facades\Cache;
use Throwable;

class Moderate
{
    /**
     * The built-in categories and what a flagged message looks like.
     *
     * @var array<string, string>
     */
    public const CATEGORIES = [
        'spam' => 'Unsolicited advertising, mass promotion, or repeated unrelated links.',
        'scam' => 'Phishing, fake giveaways, investment fraud, or requests for passwords, codes or money.',
        'toxic' => 'Insults, harassment, hate speech, or threats against a person or group.',
        'nsfw' => 'Sexual or explicitly violent content.',
    ];

    /**
     * Classify each group message and act on the ones that break the rules.
     *
     * The middleware takes the categories to check, such as "moderate:spam,scam";
     * without any, every configured category is checked. Messages from chat
     * administrators and private chats are not checked.
     *
     * @param  string  ...$categories
     */
    public function handle(Request $request, Closure $next, string ...$categories): mixed
    {
        $message = $request->message ?? $request->edited_message;
        $text = trim((string) ($message->text ?? $message->caption ?? ''));
        $chat = Chat::fromRequest($request);

        if ($text === '' || $chat === null || $chat->isPrivate() || $this->isAdministrator($chat, $message->from->id ?? null)) {
            return $next($request);
        }

        $config = (array) config('ai.telegram.moderation', []);
        $known = [...static::CATEGORIES, ...(array) ($config['categories'] ?? [])];
        $categories = $categories === [] ? array_keys($known) : $categories;

        $questions = [];

        foreach ($categories as $category) {
            if (isset($known[$category])) {
                $questions[$category] = new Boolean("Is this message {$category}?", [
                    'true' => $known[$category],
                    'false' => 'An ordinary message in a group chat.',
                ]);
            }
        }

        try {
            $result = Classification::of($text)->questions($questions)->classify();
        } catch (Throwable $e) {
            report($e);

            return $next($request);
        }

        $threshold = (float) ($config['threshold'] ?? 0.8);
        $flagged = [];

        foreach ($questions as $category => $question) {
            if ($result[$category]->isTrue($threshold)) {
                $flagged[$category] = $result[$category]->probability;
            }
        }

        if ($flagged === []) {
            return $next($request);
        }

        $action = (string) ($config['action'] ?? 'delete');

        $this->act($chat, $message, $action, (int) ($config['mute_minutes'] ?? 60));

        event(new MessageFlagged($request, $flagged, $action));

        return null;
    }

    /**
     * Act on a flagged message.
     */
    protected function act(Chat $chat, object $message, string $action, int $muteMinutes): void
    {
        $messenger = $chat->messenger();
        $userId = $message->from->id ?? null;

        $calls = [
            'delete' => fn () => $messenger->call('deleteMessage', ['chat_id' => $chat->id, 'message_id' => $message->message_id]),
            'warn' => fn () => Sender::to($chat)->markdown((string) config('ai.telegram.moderation.warning', 'This message breaks the rules of the group.')),
            'mute' => fn () => $messenger->call('restrictChatMember', [
                'chat_id' => $chat->id,
                'user_id' => $userId,
                'permissions' => ['can_send_messages' => false],
                'until_date' => time() + 60 * $muteMinutes,
            ]),
            'ban' => fn () => $messenger->call('banChatMember', ['chat_id' => $chat->id, 'user_id' => $userId]),
        ];

        // Muting and banning also remove the message; a warning keeps it.
        $steps = match ($action) {
            'warn' => ['warn'],
            'mute' => ['delete', 'mute'],
            'ban' => ['delete', 'ban'],
            default => ['delete'],
        };

        foreach ($steps as $step) {
            try {
                $calls[$step]();
            } catch (Throwable $e) {
                report($e);
            }
        }
    }

    /**
     * Determine if the user administers the chat.
     */
    protected function isAdministrator(Chat $chat, int|string|null $userId): bool
    {
        if ($userId === null || ! config('ai.telegram.moderation.skip_admins', true)) {
            return false;
        }

        $administrators = Cache::remember('laragram-ai:admins:'.$chat->id, 600, function () use ($chat) {
            try {
                return array_map(fn ($member) => $member['user']['id'] ?? null, (array) $chat->messenger()->call('getChatAdministrators', ['chat_id' => $chat->id]));
            } catch (Throwable) {
                return [];
            }
        });

        return in_array($userId, $administrators);
    }
}
