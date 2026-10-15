<?php

namespace LaraGram\Ai\Responses\Concerns;

use LaraGram\Ai\Telegram\Chat;
use LaraGram\Ai\Telegram\Sender;
use LaraGram\Request\Response as BotResponse;
use LaraGram\Routing\Router;
use Throwable;

trait SendsToTelegramWhenReady
{
    /**
     * Create a response for the given request.
     *
     * Returned from a bot or MTProto listen, the queued result is sent to the
     * chat of the update once the queued job has finished.
     *
     * @param  \LaraGram\Request\Request|\LaraGram\Http\Request|object  $request
     * @return mixed
     */
    public function toResponse($request)
    {
        if ($chat = Chat::fromRequest($request)) {
            Sender::to($chat)->action($this->telegramChatAction());

            $this->sendTo($chat);

            return new BotResponse('');
        }

        return Router::toResponse($request, '');
    }

    /**
     * Send the result to the given chat once the queued job has finished.
     *
     * @return $this
     */
    public function sendTo(mixed $chat, mixed $connection = null): static
    {
        $chat = Chat::make($chat, $connection);

        $this->then(static fn ($response) => $response->sendTo($chat));

        $this->catch(static function (Throwable $e) use ($chat) {
            if ($message = config('ai.telegram.failure_message')) {
                Sender::to($chat)->markdown((string) $message, format: 'html');
            }
        });

        return $this;
    }

    /**
     * Get the chat action shown while the job is queued.
     */
    protected function telegramChatAction(): string
    {
        return 'typing';
    }
}
