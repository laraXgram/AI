<?php

namespace LaraGram\Ai\Contracts;

use LaraGram\Contracts\JsonSchema\JsonSchema;
use LaraGram\JsonSchema\Types\Type;

interface HasStructuredOutput
{
    /**
     * Get the agent's structured output schema definition.
     *
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array;
}
