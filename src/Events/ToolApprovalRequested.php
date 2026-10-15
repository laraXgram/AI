<?php

namespace LaraGram\Ai\Events;

use LaraGram\Support\Collection;
use LaraGram\Ai\Approvals\PendingApproval;
use LaraGram\Ai\Contracts\Agent;

class ToolApprovalRequested
{
    /**
     * @param  Collection<int, PendingApproval>  $pendingApprovals
     */
    public function __construct(
        public string $invocationId,
        public Agent $agent,
        public Collection $pendingApprovals,
        public ?string $conversationId = null,
        public ?object $conversationUser = null,
    ) {}
}
