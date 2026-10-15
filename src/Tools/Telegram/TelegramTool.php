<?php

namespace LaraGram\Ai\Tools\Telegram;

use LaraGram\Ai\Approvals\Approval;
use LaraGram\Ai\Concerns\InteractsWithApprovals;
use LaraGram\Ai\Contracts\Approvable;
use LaraGram\Ai\Contracts\Tool;
use LaraGram\Ai\Telegram\Chat;
use LaraGram\Ai\Telegram\TelegramException;
use LaraGram\Ai\Tools\Request;
use LaraGram\Container\Container;
use LaraGram\Request\Request as BotRequest;
use Throwable;

abstract class TelegramTool implements Approvable, Tool
{
    use InteractsWithApprovals;

    /**
     * Create a new Telegram tool instance.
     *
     * Without a chat, the tool acts on the chat of the update being handled.
     */
    public function __construct(
        protected Chat|int|string|null $chat = null,
        protected ?string $connection = null,
    ) {}

    /**
     * Resolve the chat the tool acts on.
     *
     * @throws \LaraGram\Ai\Telegram\TelegramException
     */
    protected function chat(): Chat
    {
        $chat = $this->chat === null ? Chat::current() : Chat::make($this->chat);

        if ($chat === null) {
            throw new TelegramException('There is no Telegram chat for this tool to act on.');
        }

        return $this->connection === null ? $chat : $chat->via($this->connection);
    }

    /**
     * Call a Bot API method for the tool, describing a failure to the model instead of throwing.
     *
     * @param  array<string, mixed>  $parameters
     */
    protected function call(string $method, array $parameters): mixed
    {
        return $this->chat()->messenger()->call($method, array_filter($parameters, fn ($value) => $value !== null));
    }

    /**
     * Run the callback, turning Telegram failures into a result the model can read.
     */
    protected function attempt(callable $callback): string
    {
        try {
            return (string) $callback();
        } catch (Throwable $e) {
            return 'Telegram refused the action: '.$e->getMessage();
        }
    }

    /**
     * Get the message the current update replies to, if any.
     */
    protected function repliedMessage(): ?object
    {
        $request = Container::getInstance()->bound('request') ? Container::getInstance()->make('request') : null;

        return $request instanceof BotRequest
            ? ($request->message?->reply_to_message ?? $request->business_message?->reply_to_message)
            : null;
    }

    /**
     * Determine whether the tool needs approval for the given request.
     */
    protected function needsApproval(Request $request): Approval|bool
    {
        return false;
    }
}
