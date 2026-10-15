<?php

namespace LaraGram\Ai\Telegram;

use LaraGram\Ai\Responses\AgentResponse;
use LaraGram\Ai\Responses\StreamableAgentResponse;
use LaraGram\Ai\Streaming\Events\ReasoningDelta;
use LaraGram\Ai\Streaming\Events\TextDelta;
use LaraGram\Ai\Streaming\Events\ToolApprovalRequest;
use LaraGram\Ai\Streaming\Events\ToolCall;
use LaraGram\Ai\Telegram\Formatting\RichMarkup;
use LaraGram\Ai\Telegram\Formatting\TelegramHtml;
use LaraGram\Support\Facades\Cache;
use Throwable;

class StreamDelivery
{
    /**
     * The id of the draft being streamed.
     */
    protected int $draftId;

    /**
     * Whether the answer is streamed as a draft instead of edited messages.
     */
    protected bool $drafts;

    /**
     * The ids of the messages sent while editing, in order.
     *
     * @var array<int, int>
     */
    protected array $messageIds = [];

    /**
     * The HTML last sent for each message, to skip edits that change nothing.
     *
     * @var array<int, string>
     */
    protected array $sent = [];

    /**
     * Create a new stream delivery instance.
     */
    public function __construct(
        protected StreamableAgentResponse $stream,
        protected Chat $chat,
    ) {
        $this->draftId = random_int(1, 2147483647);

        $this->drafts = (bool) config('ai.telegram.streaming.drafts', true)
            && $chat->isPrivate()
            && ! $chat->usesMtproto()
            && $chat->inlineQueryId === null;
    }

    /**
     * Stream the response to the chat as it is generated.
     */
    public function send(): void
    {
        $sender = Sender::to($this->chat);

        $sender->answerCallback();

        if ($this->chat->inlineQueryId !== null) {
            foreach ($this->stream as $event) {
                //
            }

            InlineAnswer::text($this->chat, (string) $this->stream->text);

            return;
        }

        $sender->action('typing');

        $final = null;

        $this->stream->then(function ($response) use (&$final) {
            $final = $response;
        });

        $interval = (float) (config('ai.telegram.streaming.interval') ?? ($this->drafts ? 0.4 : 1.5));
        $text = '';
        $reasoning = '';
        $flushed = '';
        $lastFlush = 0.0;
        $stopped = false;
        $approvals = false;

        foreach ($this->stream as $event) {
            if ($event instanceof TextDelta) {
                $text .= $event->delta;
            } elseif ($event instanceof ReasoningDelta) {
                $reasoning .= $event->delta;
            } elseif ($event instanceof ToolCall) {
                $sender->action('typing');
            } elseif ($event instanceof ToolApprovalRequest) {
                $approvals = true;
            }

            if (microtime(true) - $lastFlush < $interval || ($text === $flushed && ! ($this->drafts && $reasoning !== ''))) {
                continue;
            }

            $lastFlush = microtime(true);

            if ($this->wasStopped()) {
                $stopped = true;

                break;
            }

            $this->flush($text, $reasoning);
            $flushed = $text;
        }

        $this->finish($stopped ? $text : (string) ($this->stream->text ?? $text), $approvals && ! $stopped ? $final : null);
    }

    /**
     * Show the partial answer.
     */
    protected function flush(string $text, string $reasoning): void
    {
        if ($this->drafts) {
            try {
                $this->sendDraft($text, $reasoning);

                return;
            } catch (Throwable) {
                // The bot or chat cannot use drafts; fall back to editing messages...
                $this->drafts = false;
            }
        }

        if (trim($text) !== '') {
            $this->editMessages(TelegramHtml::chunks($text));
        }
    }

    /**
     * Send the partial answer as a draft.
     */
    protected function sendDraft(string $text, string $reasoning): void
    {
        $canStop = (bool) config('ai.telegram.streaming.can_stop', true);

        if ($this->prefersRich($text) || ($reasoning !== '' && trim($text) === '')) {
            $this->chat->messenger()->call('sendRichMessageDraft', array_filter([
                'chat_id' => $this->chat->id,
                'draft_id' => $this->draftId,
                'message_thread_id' => $this->chat->threadId,
                'rich_message' => RichMarkup::message($text, draft: true, thinking: $reasoning !== '' && trim($text) === '' ? $reasoning : null),
                'can_stop' => $canStop ?: null,
            ]));

            return;
        }

        $chunks = TelegramHtml::chunks($text);

        $this->chat->messenger()->call('sendMessageDraft', array_filter([
            'chat_id' => $this->chat->id,
            'draft_id' => $this->draftId,
            'message_thread_id' => $this->chat->threadId,
            'text' => (string) end($chunks),
            'parse_mode' => 'HTML',
            'can_stop' => $canStop ?: null,
        ], fn ($value) => $value !== null && $value !== false));
    }

    /**
     * Edit the sent messages to show the given chunks, sending new messages as needed.
     *
     * @param  array<int, string>  $chunks
     */
    protected function editMessages(array $chunks): void
    {
        $messenger = $this->chat->messenger();

        foreach (array_values($chunks) as $index => $chunk) {
            if (isset($this->messageIds[$index])) {
                if (($this->sent[$index] ?? null) === $chunk) {
                    continue;
                }

                try {
                    $messenger->call('editMessageText', [
                        'chat_id' => $this->chat->id,
                        'message_id' => $this->messageIds[$index],
                        'text' => $chunk,
                        'parse_mode' => 'HTML',
                        'link_preview_options' => ['is_disabled' => true],
                    ]);
                } catch (Throwable) {
                    // "Message is not modified" and rate limits must not stop the stream...
                }

                $this->sent[$index] = $chunk;

                continue;
            }

            $result = $messenger->call('sendMessage', [
                ...$this->chat->parameters($index === 0),
                'text' => $chunk,
                'parse_mode' => 'HTML',
                'link_preview_options' => ['is_disabled' => true],
            ]);

            if ($id = $result['message_id'] ?? null) {
                $this->messageIds[$index] = $id;
                $this->sent[$index] = $chunk;
            }
        }
    }

    /**
     * Persist the complete answer.
     */
    protected function finish(string $text, ?AgentResponse $response): void
    {
        if ($this->drafts || $this->messageIds === []) {
            if (trim($text) !== '') {
                Sender::to($this->chat)->markdown($text);
            }
        } elseif (trim($text) !== '') {
            if (Sender::format() !== 'html' && $this->prefersRich($text)) {
                // The final answer needs a rich message; replace the edited plain messages with it...
                Sender::to($this->chat)->markdown($text);

                foreach ($this->messageIds as $id) {
                    try {
                        $this->chat->messenger()->call('deleteMessage', ['chat_id' => $this->chat->id, 'message_id' => $id]);
                    } catch (Throwable) {
                        //
                    }
                }
            } else {
                $this->editMessages(TelegramHtml::chunks($text));
            }
        }

        if ($response !== null && $response->hasPendingApprovals()) {
            Approvals::request($response, $this->chat->withoutReply());
        }
    }

    /**
     * Determine if the answer should be shown as a rich message.
     */
    protected function prefersRich(string $text): bool
    {
        return match (Sender::format()) {
            'rich' => true,
            'html' => false,
            default => RichMarkup::needsRichMessage($text) || TelegramHtml::length(TelegramHtml::render($text)) > TelegramHtml::MESSAGE_LIMIT,
        };
    }

    /**
     * Determine if the user pressed the draft's stop button.
     */
    protected function wasStopped(): bool
    {
        return $this->drafts && Cache::has(static::stopKey($this->chat->id, $this->draftId));
    }

    /**
     * Mark the given draft as stopped by the user.
     */
    public static function stop(int|string $chatId, int|string $draftId): void
    {
        Cache::put(static::stopKey($chatId, $draftId), true, 600);
    }

    /**
     * Get the cache key that marks a stopped draft.
     */
    protected static function stopKey(int|string $chatId, int|string $draftId): string
    {
        return 'laragram-ai:stopped:'.$chatId.':'.$draftId;
    }
}
