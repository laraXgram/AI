<?php

namespace LaraGram\Ai\Tools\Telegram;

use LaraGram\Ai\Tools\Request;
use LaraGram\Contracts\JsonSchema\JsonSchema;

class GetChat extends TelegramTool
{
    /**
     * Get the description of the tool's purpose.
     */
    public function description(): string
    {
        return 'Get information about the current Telegram chat: its type, title, username, description and number of members.';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): string
    {
        return $this->attempt(function () {
            $chat = (array) $this->call('getChat', ['chat_id' => $this->chat()->id]);

            try {
                $chat['member_count'] = $this->call('getChatMemberCount', ['chat_id' => $this->chat()->id]);
            } catch (\Throwable) {
                //
            }

            return json_encode(array_intersect_key($chat, array_flip([
                'id', 'type', 'title', 'username', 'first_name', 'last_name', 'description', 'bio', 'member_count', 'is_forum',
            ])), JSON_UNESCAPED_UNICODE);
        });
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
