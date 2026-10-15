<?php

namespace LaraGram\Ai\Contracts;

use LaraGram\Contracts\JsonSchema\JsonSchema;
use LaraGram\JsonSchema\Types\Type;

interface HasStructuredOutput
{
    /**
     * Get the agent's structured output schema definition, or the Telegram output it produces.
     *
     * @return array<string, Type>|\LaraGram\Ai\Contracts\TelegramOutput
     */
    public function schema(JsonSchema $schema): array|TelegramOutput;
}
