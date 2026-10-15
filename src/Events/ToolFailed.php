<?php

namespace LaraGram\Ai\Events;

use LaraGram\Ai\Contracts\Agent;
use LaraGram\Ai\Contracts\Tool;
use Throwable;

class ToolFailed
{
    /**
     * @param  array<string, mixed>  $arguments
     * @param  float  $time  Wall time spent in the tool's handler before it threw, in milliseconds.
     */
    public function __construct(
        public string $invocationId,
        public string $toolInvocationId,
        public Agent $agent,
        public Tool $tool,
        public array $arguments,
        public Throwable $exception,
        public float $time,
    ) {}
}
