<?php

namespace LaraGram\Ai\Tools\Telegram;

use LaraGram\Ai\Telegram\Sender;
use LaraGram\Ai\Tools\Request;
use LaraGram\Contracts\JsonSchema\JsonSchema;

class SendMessage extends TelegramTool
{
    /**
     * Get the description of the tool's purpose.
     */
    public function description(): string
    {
        return 'Send a separate message to the current chat, written in Markdown. Use it for content that should stand on its own, not for your answer.';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): string
    {
        return $this->attempt(function () use ($request) {
            $ids = Sender::to($this->chat()->withoutReply())->markdown((string) $request['text']);

            return 'Sent message '.implode(', ', $ids).'.';
        });
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'text' => $schema->string()->description('The message, in Markdown.')->required(),
        ];
    }
}
