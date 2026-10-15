<?php

namespace LaraGram\Ai\Contracts;

use LaraGram\Ai\Telegram\Chat;
use LaraGram\Contracts\JsonSchema\JsonSchema;

interface TelegramOutput
{
    /**
     * Get the structured output schema the model fills in.
     *
     * @return array<string, \LaraGram\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array;

    /**
     * Send the model's structured output to the given chat.
     *
     * @param  array<string, mixed>  $data
     */
    public function send(array $data, Chat $chat): void;
}
