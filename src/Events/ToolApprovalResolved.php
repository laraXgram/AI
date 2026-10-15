<?php

namespace LaraGram\Ai\Events;

use LaraGram\Support\Collection;
use LaraGram\Ai\Contracts\Agent;
use LaraGram\Ai\Responses\Data\ToolResult;

class ToolApprovalResolved
{
    /**
     * @param  Collection<int, ToolResult>  $toolResults
     */
    public function __construct(
        public string $invocationId,
        public Agent $agent,
        public Collection $toolResults,
        public ?string $conversationId = null,
        public ?object $conversationUser = null,
    ) {}
}
