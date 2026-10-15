<?php

namespace LaraGram\Ai\Tools\Telegram;

use LaraGram\Ai\Approvals\Approval;
use LaraGram\Ai\Tools\Request;
use LaraGram\Contracts\JsonSchema\JsonSchema;

class RestrictChatMember extends TelegramTool
{
    /**
     * Get the description of the tool's purpose.
     */
    public function description(): string
    {
        return 'Mute a user in the current group for a number of minutes, so they cannot send messages. Without a user ID, the author of the replied-to message is muted.';
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

        $minutes = max(1, (int) ($request['minutes'] ?? 60));

        return $this->attempt(function () use ($userId, $minutes) {
            $this->call('restrictChatMember', [
                'chat_id' => $this->chat()->id,
                'user_id' => $userId,
                'permissions' => ['can_send_messages' => false],
                'until_date' => time() + 60 * $minutes,
            ]);

            return "Muted user {$userId} for {$minutes} minutes.";
        });
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'user_id' => $schema->integer()->description('The Telegram user ID.')->nullable(),
            'minutes' => $schema->integer()->min(1)->description('How long to mute the user.')->required(),
        ];
    }

    /**
     * Determine whether the tool needs approval for the given request.
     */
    protected function needsApproval(Request $request): Approval|bool
    {
        return Approval::required('This will mute a user in the group.');
    }
}
