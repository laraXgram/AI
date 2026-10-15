<?php

namespace LaraGram\Ai\Responses;

use ArrayAccess;
use LaraGram\Contracts\Support\Arrayable;
use LaraGram\Contracts\Support\Jsonable;
use LaraGram\Support\Collection;
use JsonSerializable;
use LaraGram\Ai\Responses\Data\Meta;
use LaraGram\Ai\Responses\Data\TextUsage;

class StructuredAgentResponse extends AgentResponse implements Arrayable, ArrayAccess, Jsonable, JsonSerializable
{
    use ProvidesStructuredResponse;

    public function __construct(string $invocationId, array $structured, string $text, TextUsage $usage, Meta $meta)
    {
        parent::__construct($invocationId, $text, $usage, $meta);

        $this->structured = $structured;
        $this->toolCalls = new Collection;
        $this->toolResults = new Collection;
    }

    /**
     * Get the instance as an array.
     */
    public function toArray(): array
    {
        return $this->structured;
    }

    /**
     * Convert the object to its JSON representation.
     *
     * @param  int  $options
     */
    public function toJson($options = 0): string
    {
        return json_encode($this->structured, $options);
    }

    /**
     * Get the JSON serializable representation of the instance.
     */
    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }

    /**
     * Get the string representation of the object.
     */
    #[\Override]
    public function __toString(): string
    {
        return (string) json_encode($this->structured);
    }
}
