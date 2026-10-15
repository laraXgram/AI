<?php

namespace LaraGram\Ai\Responses\Concerns;

use LaraGram\Ai\Telegram\Chat;
use LaraGram\Request\Response as BotResponse;
use LaraGram\Routing\Router;

trait SendsToTelegram
{
    /**
     * Create a response for the given request.
     *
     * Returned from a bot or MTProto listen, the response is sent to the chat
     * of the update. Returned from an HTTP route, it behaves like any value
     * returned from a route.
     *
     * @param  \LaraGram\Request\Request|\LaraGram\Http\Request|object  $request
     * @return mixed
     */
    public function toResponse($request)
    {
        if ($chat = Chat::fromRequest($request)) {
            $this->deliverTo($chat);

            return new BotResponse('');
        }

        return Router::toResponse($request, $this->toHttpValue());
    }

    /**
     * Send the response to the given chat.
     *
     * The chat may be an identifier, a Telegram chat or user object, a bot or MTProto
     * update, or a chat instance. The connection names the bot connection, or an
     * MTProto session as "mtproto:name".
     *
     * @return $this
     */
    public function sendTo(mixed $chat, mixed $connection = null): static
    {
        $this->deliverTo(Chat::make($chat, $connection));

        return $this;
    }

    /**
     * Send the response to the chat of the update currently being handled.
     *
     * @return $this
     */
    public function reply(): static
    {
        $chat = Chat::current() ?? throw new \LogicException('There is no Telegram update to reply to.');

        $this->deliverTo($chat);

        return $this;
    }

    /**
     * Get the value an HTTP route would return for this response.
     */
    protected function toHttpValue(): mixed
    {
        return (string) $this;
    }

    /**
     * Deliver the response to the given chat.
     */
    abstract protected function deliverTo(Chat $chat): void;
}
