<?php

namespace LaraGram\Ai\Streaming\Events;

use LaraGram\Support\Collection;
use LaraGram\Ai\Approvals\PendingApproval;
use LaraGram\Ai\Responses\Data\Step;

class ToolApprovalRequest extends StreamEvent
{
    /**
     * @param  Collection<int, PendingApproval>  $pendingApprovals
     * @param  Collection<int, Step>  $steps  replay state for the paused turn; never serialized to clients
     */
    public function __construct(
        public string $id,
        public Collection $pendingApprovals,
        public int $timestamp,
        public Collection $steps = new Collection,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'invocation_id' => $this->invocationId,
            'type' => 'tool_approval_request',
            'approvals' => $this->pendingApprovals->values()->toArray(),
            'timestamp' => $this->timestamp,
        ];
    }
}
