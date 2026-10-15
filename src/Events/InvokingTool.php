<?php

namespace LaraGram\Ai\Events;

use LaraGram\Ai\Contracts\Agent;
use LaraGram\Ai\Contracts\Tool;

class InvokingTool
{
    /**
     * @param  array<string, mixed>  $arguments
     */
    public function __construct(
        public string $invocationId,
        public string $toolInvocationId,
        public Agent $agent,
        public Tool $tool,
        public array $arguments,
    ) {}
}
