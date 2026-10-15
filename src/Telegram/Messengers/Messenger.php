<?php

namespace LaraGram\Ai\Telegram\Messengers;

use LaraGram\Ai\Telegram\Chat;

abstract class Messenger
{
    /**
     * Create a new messenger instance.
     */
    public function __construct(protected Chat $chat) {}

    /**
     * Call a Bot API method, translating it for the transport when needed.
     *
     * Parameters use the Bot API's names and shapes; files are given as
     * {@see \LaraGram\Ai\Telegram\Upload} instances. The method's result is
     * returned as an array, or true for methods that only confirm.
     *
     * @param  array<string, mixed>  $parameters
     *
     * @throws \LaraGram\Ai\Telegram\TelegramException
     */
    abstract public function call(string $method, array $parameters = []): mixed;

    /**
     * Determine if the transport supports the given Bot API method.
     */
    abstract public function supports(string $method): bool;

    /**
     * Get the chat the messenger delivers to.
     */
    public function chat(): Chat
    {
        return $this->chat;
    }
}
