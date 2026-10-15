<?php

namespace LaraGram\Ai\Telegram;

use LaraGram\Contracts\JsonSchema\JsonSchema;

class RichMessage extends Output
{
    /**
     * Get the structured output schema the model fills in.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'content' => $schema->string()->description(
                'The message in Markdown. Headings, lists, task lists, tables, quotes, code blocks and $$formulas$$ are shown as a rich document.'
            )->required(),
        ];
    }

    /**
     * Send the content as a rich message.
     */
    public function send(array $data, Chat $chat): void
    {
        Sender::to($chat)->markdown((string) ($data['content'] ?? ''), $this->replyMarkup, 'rich');
    }
}
