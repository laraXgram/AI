<?php

namespace LaraGram\Ai\Events;

use LaraGram\Ai\Contracts\Agent;
use LaraGram\Ai\Contracts\Tool;

class ToolInvoked
{
    /**
     * @param  array<string, mixed>  $arguments
     * @param  float  $time  Wall time spent in the tool's handler, in milliseconds.
     */
    public function __construct(
        public string $invocationId,
        public string $toolInvocationId,
        public Agent $agent,
        public Tool $tool,
        public array $arguments,
        public mixed $result,
        public float $time,
    ) {}
}
