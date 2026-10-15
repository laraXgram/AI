<?php

namespace LaraGram\Ai\Tools\Telegram;

use LaraGram\Ai\Tools\Request;
use LaraGram\Contracts\JsonSchema\JsonSchema;

class GetChatMember extends TelegramTool
{
    /**
     * Get the description of the tool's purpose.
     */
    public function description(): string
    {
        return 'Get the status of a user in the current chat (creator, administrator, member, restricted, left or kicked). Without a user ID, the author of the replied-to message is used.';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): string
    {
        $userId = $request['user_id'] ?? $this->repliedMessage()?->from?->id;

        if ($userId === null) {
            return 'No user was given and the message does not reply to anyone.';
        }

        return $this->attempt(fn () => json_encode($this->call('getChatMember', [
            'chat_id' => $this->chat()->id,
            'user_id' => $userId,
        ]), JSON_UNESCAPED_UNICODE));
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'user_id' => $schema->integer()->description('The Telegram user ID.')->nullable(),
        ];
    }
}
