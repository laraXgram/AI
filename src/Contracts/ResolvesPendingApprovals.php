<?php

namespace LaraGram\Ai\Contracts;

use LaraGram\Ai\Approvals\PendingApproval;

interface ResolvesPendingApprovals
{
    /**
     * Get the tool calls the given conversation's newest turn is still waiting on.
     *
     * @return list<PendingApproval>
     */
    public function pendingApprovalsFor(string $conversationId): array;
}
