<?php

namespace LaraGram\Ai\Tools\Telegram;

use LaraGram\Ai\Tools\Request;
use LaraGram\Contracts\JsonSchema\JsonSchema;

class ReactToMessage extends TelegramTool
{
    /**
     * Get the description of the tool's purpose.
     */
    public function description(): string
    {
        return 'React to the user\'s message with an emoji, such as 👍, ❤, 🔥, 🎉, 🤔 or 👀.';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): string
    {
        $messageId = $request['message_id'] ?? $this->chat()->replyTo;

        if ($messageId === null) {
            return 'There is no message to react to.';
        }

        return $this->attempt(function () use ($request, $messageId) {
            $this->call('setMessageReaction', [
                'chat_id' => $this->chat()->id,
                'message_id' => $messageId,
                'reaction' => [['type' => 'emoji', 'emoji' => (string) $request['emoji']]],
            ]);

            return 'Reacted.';
        });
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'emoji' => $schema->string()->description('A reaction emoji Telegram allows.')->required(),
            'message_id' => $schema->integer()->description('The message to react to; defaults to the user\'s message.')->nullable(),
        ];
    }
}
