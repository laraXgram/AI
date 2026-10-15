<?php

namespace LaraGram\Ai\Telegram;

use InvalidArgumentException;
use LaraGram\Ai\Telegram\Messengers\BotApiMessenger;
use LaraGram\Ai\Telegram\Messengers\Messenger;
use LaraGram\Ai\Telegram\Messengers\MtprotoMessenger;
use LaraGram\Container\Container;
use LaraGram\Request\Request;

class Chat
{
    /**
     * Create a new chat instance.
     *
     * A chat is the destination of a response: the chat itself, the message to
     * reply to, and the bot connection or MTProto session that sends it. It only
     * holds scalar values, so it may travel with queued jobs.
     */
    public function __construct(
        public int|string $id,
        public ?string $type = null,
        public ?int $replyTo = null,
        public ?int $threadId = null,
        public ?string $businessConnectionId = null,
        public ?string $connection = null,
        public ?string $session = null,
        public ?string $inlineQueryId = null,
        public ?string $callbackQueryId = null,
        public int|string|null $userId = null,
    ) {}

    /**
     * Get the chat of the update currently being handled, if any.
     */
    public static function current(): ?static
    {
        $container = Container::getInstance();

        foreach (['request', 'client.request'] as $binding) {
            if ($container->bound($binding) && ($chat = static::fromRequest($container->make($binding)))) {
                return $chat;
            }
        }

        return null;
    }

    /**
     * Get the chat of the given bot or MTProto update.
     */
    public static function fromRequest(mixed $request): ?static
    {
        if ($request instanceof Request) {
            return static::fromBotRequest($request);
        }

        if ($request instanceof \LaraGram\MTProto\Foundation\ClientRequest) {
            return static::fromClientRequest($request);
        }

        return null;
    }

    /**
     * Resolve a chat from an identifier, a Telegram chat or user object, or a chat instance.
     *
     * The connection may name a bot connection, or be an MTProto client request,
     * client or session name prefixed with "mtproto:".
     */
    public static function make(mixed $chat, mixed $connection = null): static
    {
        $resolved = match (true) {
            $chat instanceof static => clone $chat,
            $chat instanceof Request, $chat instanceof \LaraGram\MTProto\Foundation\ClientRequest => static::fromRequest($chat)
                ?? throw new InvalidArgumentException('The given update does not belong to a chat.'),
            is_int($chat), is_string($chat) => new static($chat),
            is_array($chat) && isset($chat['id']) => new static($chat['id'], $chat['type'] ?? null),
            is_object($chat) && isset($chat->id) => new static($chat->id, $chat->type ?? null),
            default => throw new InvalidArgumentException('Unable to resolve a Telegram chat from the given value.'),
        };

        return $connection === null ? $resolved : $resolved->via($connection);
    }

    /**
     * Send through the given bot connection, MTProto client or "mtproto:session".
     */
    public function via(mixed $connection): static
    {
        $chat = clone $this;

        if ($connection instanceof \LaraGram\MTProto\Foundation\ClientRequest) {
            $chat->session = $connection->session();
            $chat->connection = null;
        } elseif (is_string($connection) && str_starts_with($connection, 'mtproto:')) {
            $chat->session = substr($connection, 8) ?: 'default';
            $chat->connection = null;
        } elseif (is_string($connection)) {
            $chat->connection = $connection;
            $chat->session = null;
        }

        return $chat;
    }

    /**
     * Get a copy of the chat that does not reply to a message.
     */
    public function withoutReply(): static
    {
        $chat = clone $this;
        $chat->replyTo = null;

        return $chat;
    }

    /**
     * Determine if the chat is a private chat with a user.
     */
    public function isPrivate(): bool
    {
        return $this->type === 'private' || ($this->type === null && is_int($this->id) && $this->id > 0);
    }

    /**
     * Determine if responses go through an MTProto session.
     */
    public function usesMtproto(): bool
    {
        return $this->session !== null;
    }

    /**
     * Get the messenger that delivers calls to this chat.
     */
    public function messenger(): Messenger
    {
        return $this->usesMtproto() ? new MtprotoMessenger($this) : new BotApiMessenger($this);
    }

    /**
     * Get the Bot API parameters that address a message to this chat.
     *
     * @return array<string, mixed>
     */
    public function parameters(bool $reply = true): array
    {
        return array_filter([
            'chat_id' => $this->id,
            'message_thread_id' => $this->threadId,
            'business_connection_id' => $this->businessConnectionId,
            'reply_parameters' => $reply && $this->replyTo
                ? ['message_id' => $this->replyTo, 'allow_sending_without_reply' => true]
                : null,
        ], fn ($value) => $value !== null);
    }

    /**
     * Get the chat of a bot update.
     */
    protected static function fromBotRequest(Request $request): ?static
    {
        if ($request->update_id === null) {
            return null;
        }

        try {
            $connection = $request->getConnection();
        } catch (\Throwable) {
            $connection = null;
        }

        $user = static::userOf($request);

        if ($query = $request->inline_query) {
            return new static($query->from->id, 'private', connection: $connection, inlineQueryId: $query->id, userId: $query->from->id);
        }

        $message = $request->callback_query?->message
            ?? $request->message
            ?? $request->business_message
            ?? $request->edited_message
            ?? $request->channel_post;

        $chat = $message?->chat
            ?? $request->message_reaction?->chat
            ?? $request->stopped_message_generation?->chat
            ?? $request->chat_join_request?->chat;

        if ($chat === null) {
            return $user ? new static($user->id, 'private', connection: $connection, userId: $user->id) : null;
        }

        return new static(
            $chat->id,
            $chat->type ?? null,
            replyTo: $request->callback_query ? null : ($message->message_id ?? null),
            threadId: ($message->is_topic_message ?? false) ? ($message->message_thread_id ?? null) : null,
            businessConnectionId: $message->business_connection_id ?? null,
            connection: $connection,
            callbackQueryId: $request->callback_query?->id,
            userId: $user?->id,
        );
    }

    /**
     * Get the Telegram user who caused the given bot update.
     */
    public static function userOf(Request $request): ?object
    {
        return $request->message?->from
            ?? $request->edited_message?->from
            ?? $request->business_message?->from
            ?? $request->callback_query?->from
            ?? $request->inline_query?->from
            ?? $request->chosen_inline_result?->from
            ?? $request->pre_checkout_query?->from
            ?? $request->shipping_query?->from
            ?? $request->poll_answer?->user
            ?? $request->message_reaction?->user
            ?? $request->my_chat_member?->from
            ?? $request->chat_member?->from
            ?? $request->chat_join_request?->from
            ?? $request->channel_post?->from;
    }

    /**
     * Get the chat of an MTProto update.
     */
    protected static function fromClientRequest(\LaraGram\MTProto\Foundation\ClientRequest $request): ?static
    {
        $chatId = $request->chatId();

        if ($chatId === null) {
            return null;
        }

        return new static(
            $chatId,
            $chatId > 0 ? 'private' : 'group',
            replyTo: $request->messageId(),
            session: $request->session(),
            callbackQueryId: isset($request->toArray()['query_id']) ? (string) $request->toArray()['query_id'] : null,
        );
    }
}
