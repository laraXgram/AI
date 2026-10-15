<?php

namespace LaraGram\Ai\Tools\Telegram;

use LaraGram\Ai\Approvals\Approval;
use LaraGram\Ai\Tools\Request;
use LaraGram\Contracts\JsonSchema\JsonSchema;

class BanChatMember extends TelegramTool
{
    /**
     * Get the description of the tool's purpose.
     */
    public function description(): string
    {
        return 'Ban a user from the current group. Without a user ID, the author of the replied-to message is banned.';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): string
    {
        $userId = $request['user_id'] ?? $this->repliedMessage()?->from?->id;

        if ($userId === null) {
            return 'No user was given and the user did not reply to anyone.';
        }

        return $this->attempt(function () use ($request, $userId) {
            $this->call('banChatMember', [
                'chat_id' => $this->chat()->id,
                'user_id' => $userId,
                'until_date' => isset($request['minutes']) ? time() + 60 * (int) $request['minutes'] : null,
                'revoke_messages' => $request['delete_messages'] ?? null,
            ]);

            return "Banned user {$userId}.";
        });
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'user_id' => $schema->integer()->description('The Telegram user ID.')->nullable(),
            'minutes' => $schema->integer()->description('Ban for this many minutes; omit to ban forever.')->nullable(),
            'delete_messages' => $schema->boolean()->description('Also delete the user\'s messages.')->nullable(),
        ];
    }

    /**
     * Determine whether the tool needs approval for the given request.
     */
    protected function needsApproval(Request $request): Approval|bool
    {
        return Approval::required('This will remove a user from the group.');
    }
}
