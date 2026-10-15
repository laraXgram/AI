<?php

namespace LaraGram\Ai\Gateway\Concerns;

use LaraGram\Ai\ObjectSchema;

trait ComposesSchemaInstructions
{
    protected function composeInstructions(?string $instructions, ?array $schema): ?string
    {
        if (blank($schema)) {
            return $instructions;
        }

        $schemaJson = json_encode((new ObjectSchema($schema))->toSchema(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        $schemaInstruction = sprintf(
            "You MUST respond EXCLUSIVELY with a JSON object that strictly adheres to the following schema. Do NOT explain or add other content. Validate your response against this schema:\n%s",
            $schemaJson,
        );

        return blank($instructions)
            ? $schemaInstruction
            : $instructions."\n\n".$schemaInstruction;
    }
}
