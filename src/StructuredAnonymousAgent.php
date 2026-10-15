<?php

namespace LaraGram\Ai;

use Closure;
use LaraGram\Contracts\JsonSchema\JsonSchema;
use LaraGram\Ai\Contracts\HasStructuredOutput;
use LaraGram\Ai\Contracts\TelegramOutput;
use LaraGram\Support\SerializableClosure\SerializableClosure;

class StructuredAnonymousAgent extends AnonymousAgent implements HasStructuredOutput
{
    /**
     * @var SerializableClosure|null
     */
    public $schema;

    public function __construct(
        public string $instructions,
        public iterable $messages,
        public iterable $tools,
        Closure|TelegramOutput|null $schema = null,
    ) {
        $this->schema = $schema instanceof Closure ? new SerializableClosure($schema) : $schema;
    }

    public function schema(JsonSchema $schema): array|TelegramOutput
    {
        return $this->schema instanceof TelegramOutput
            ? $this->schema
            : call_user_func($this->schema, $schema);
    }
}
