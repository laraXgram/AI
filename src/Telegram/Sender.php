<?php

namespace LaraGram\Ai\Telegram;

use LaraGram\Ai\Telegram\Formatting\RichMarkup;
use LaraGram\Ai\Telegram\Formatting\TelegramHtml;
use Throwable;

class Sender
{
    /**
     * Create a new sender instance.
     */
    public function __construct(protected Chat $chat) {}

    /**
     * Create a sender for the given chat.
     */
    public static function to(Chat $chat): static
    {
        return new static($chat);
    }

    /**
     * Send Markdown text in the configured format, splitting it when it is too long.
     *
     * The reply markup is attached to the last message that is sent.
     *
     * @param  array<string, mixed>|null  $replyMarkup
     * @return array<int, int> The ids of the sent messages.
     */
    public function markdown(string $markdown, ?array $replyMarkup = null, ?string $format = null): array
    {
        $markdown = trim($markdown);

        if ($markdown === '') {
            return [];
        }

        if ($this->usesRichMessage($markdown, $format)) {
            try {
                $result = $this->call('sendRichMessage', [
                    ...$this->chat->parameters(),
                    'rich_message' => RichMarkup::message($markdown),
                    'reply_markup' => $replyMarkup,
                ]);

                return array_filter([$result['message_id'] ?? null]);
            } catch (Throwable $e) {
                if (($format ?? static::format()) === 'rich') {
                    throw $e;
                }
            }
        }

        return $this->html(TelegramHtml::chunks($markdown), $replyMarkup);
    }

    /**
     * Send already formatted Telegram HTML chunks.
     *
     * @param  array<int, string>  $chunks
     * @param  array<string, mixed>|null  $replyMarkup
     * @return array<int, int>
     */
    public function html(array $chunks, ?array $replyMarkup = null): array
    {
        $ids = [];
        $last = count($chunks) - 1;

        foreach (array_values($chunks) as $index => $chunk) {
            $result = $this->call('sendMessage', array_filter([
                ...$this->chat->parameters($index === 0),
                'text' => $chunk,
                'parse_mode' => 'HTML',
                'link_preview_options' => ['is_disabled' => true],
                'reply_markup' => $index === $last ? $replyMarkup : null,
            ], fn ($value) => $value !== null));

            if ($id = $result['message_id'] ?? null) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    /**
     * Show a chat action, such as "typing", while a response is being prepared.
     */
    public function action(string $action = 'typing'): void
    {
        if ($this->chat->inlineQueryId !== null) {
            return;
        }

        try {
            $this->call('sendChatAction', array_filter([
                'chat_id' => $this->chat->id,
                'message_thread_id' => $this->chat->threadId,
                'business_connection_id' => $this->chat->businessConnectionId,
                'action' => $action,
            ]));
        } catch (Throwable) {
            // A missing chat action never prevents the response from being sent.
        }
    }

    /**
     * Answer the callback query that triggered the update, if any.
     */
    public function answerCallback(?string $text = null): void
    {
        if ($this->chat->callbackQueryId === null) {
            return;
        }

        try {
            $this->call('answerCallbackQuery', array_filter([
                'callback_query_id' => $this->chat->callbackQueryId,
                'text' => $text,
            ]));
        } catch (Throwable) {
            //
        }

        $this->chat->callbackQueryId = null;
    }

    /**
     * Call a Bot API method through the chat's messenger.
     *
     * @param  array<string, mixed>  $parameters
     */
    public function call(string $method, array $parameters): mixed
    {
        return $this->chat->messenger()->call($method, array_filter($parameters, fn ($value) => $value !== null));
    }

    /**
     * Get the chat the sender delivers to.
     */
    public function chat(): Chat
    {
        return $this->chat;
    }

    /**
     * Determine if the Markdown should be sent as a rich message.
     */
    protected function usesRichMessage(string $markdown, ?string $format): bool
    {
        return match ($format ?? static::format()) {
            'rich' => true,
            'html' => false,
            default => RichMarkup::needsRichMessage($markdown) || TelegramHtml::length(TelegramHtml::render($markdown)) > TelegramHtml::MESSAGE_LIMIT,
        };
    }

    /**
     * Get the configured text format: "auto", "html" or "rich".
     */
    public static function format(): string
    {
        return (string) config('ai.telegram.format', 'auto');
    }
}
