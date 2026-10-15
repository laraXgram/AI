<?php

namespace LaraGram\Ai\Tools\Telegram;

use LaraGram\Ai\Tools\Request;
use LaraGram\Contracts\JsonSchema\JsonSchema;

class GetChatAdministrators extends TelegramTool
{
    /**
     * Get the description of the tool's purpose.
     */
    public function description(): string
    {
        return 'List the administrators of the current group or channel.';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): string
    {
        return $this->attempt(fn () => json_encode(array_map(fn ($member) => [
            'user_id' => $member['user']['id'] ?? null,
            'name' => trim(($member['user']['first_name'] ?? '').' '.($member['user']['last_name'] ?? '')),
            'username' => $member['user']['username'] ?? null,
            'status' => $member['status'] ?? null,
        ], (array) $this->call('getChatAdministrators', ['chat_id' => $this->chat()->id])), JSON_UNESCAPED_UNICODE));
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
