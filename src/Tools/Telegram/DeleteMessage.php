<?php

namespace LaraGram\Ai\Tools\Telegram;

use LaraGram\Ai\Approvals\Approval;
use LaraGram\Ai\Tools\Request;
use LaraGram\Contracts\JsonSchema\JsonSchema;

class DeleteMessage extends TelegramTool
{
    /**
     * Get the description of the tool's purpose.
     */
    public function description(): string
    {
        return 'Delete a message from the current chat. Without a message ID, the replied-to message is deleted.';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): string
    {
        $messageId = $request['message_id'] ?? $this->repliedMessage()?->message_id;

        if ($messageId === null) {
            return 'No message was given and the user did not reply to one.';
        }

        return $this->attempt(function () use ($messageId) {
            $this->call('deleteMessage', ['chat_id' => $this->chat()->id, 'message_id' => $messageId]);

            return 'Deleted.';
        });
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'message_id' => $schema->integer()->description('The message ID; defaults to the replied-to message.')->nullable(),
        ];
    }

    /**
     * Determine whether the tool needs approval for the given request.
     */
    protected function needsApproval(Request $request): Approval|bool
    {
        return Approval::required('This will permanently delete a message.');
    }
}
