<?php

namespace LaraGram\Ai\Tools;

use LaraGram\Ai\Telegram\Chat;
use LaraGram\Ai\Tools\Telegram\BanChatMember;
use LaraGram\Ai\Tools\Telegram\DeleteMessage;
use LaraGram\Ai\Tools\Telegram\GetChat;
use LaraGram\Ai\Tools\Telegram\GetChatAdministrators;
use LaraGram\Ai\Tools\Telegram\GetChatMember;
use LaraGram\Ai\Tools\Telegram\PinMessage;
use LaraGram\Ai\Tools\Telegram\ReactToMessage;
use LaraGram\Ai\Tools\Telegram\RestrictChatMember;
use LaraGram\Ai\Tools\Telegram\SendMessage;
use LaraGram\Ai\Tools\Telegram\SendPoll;
use LaraGram\Support\Collection;

class Telegram
{
    /**
     * Create all of the Telegram tools for the given chat, or the chat of the current update.
     *
     * Pinning, deleting, banning and muting require approval unless their approval is removed.
     *
     * @return Collection<int, \LaraGram\Ai\Contracts\Tool>
     */
    public static function all(Chat|int|string|null $chat = null, ?string $connection = null): Collection
    {
        return static::readOnly($chat, $connection)
            ->merge(static::messaging($chat, $connection))
            ->merge(static::moderation($chat, $connection));
    }

    /**
     * Create the tools that only read information about the chat.
     *
     * @return Collection<int, \LaraGram\Ai\Contracts\Tool>
     */
    public static function readOnly(Chat|int|string|null $chat = null, ?string $connection = null): Collection
    {
        return new Collection([
            new GetChat($chat, $connection),
            new GetChatMember($chat, $connection),
            new GetChatAdministrators($chat, $connection),
        ]);
    }

    /**
     * Create the tools that send messages, polls and reactions to the chat.
     *
     * @return Collection<int, \LaraGram\Ai\Contracts\Tool>
     */
    public static function messaging(Chat|int|string|null $chat = null, ?string $connection = null): Collection
    {
        return new Collection([
            new SendMessage($chat, $connection),
            new SendPoll($chat, $connection),
            new ReactToMessage($chat, $connection),
        ]);
    }

    /**
     * Create the tools that moderate the chat; each one requires approval.
     *
     * @return Collection<int, \LaraGram\Ai\Contracts\Tool>
     */
    public static function moderation(Chat|int|string|null $chat = null, ?string $connection = null): Collection
    {
        return new Collection([
            new PinMessage($chat, $connection),
            new DeleteMessage($chat, $connection),
            new BanChatMember($chat, $connection),
            new RestrictChatMember($chat, $connection),
        ]);
    }
}
