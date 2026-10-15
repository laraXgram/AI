<?php

namespace LaraGram\Ai\Exceptions;

use LaraGram\Http\Request;
use LaraGram\Support\Collection;
use LaraGram\Ai\Approvals\PendingApproval;
use LaraGram\Http\BaseResponse;

class ApprovalMismatchException extends AiException
{
    /**
     * Create a new approval mismatch exception.
     *
     * @param  Collection<int, PendingApproval>  $pendingApprovals
     */
    public function __construct(string $message, public Collection $pendingApprovals)
    {
        parent::__construct($message);
    }

    /**
     * Render the exception as an HTTP response.
     */
    public function render(Request $request): Response
    {
        return response()->json([
            'message' => $this->getMessage(),
            'approvals' => $this->pendingApprovals->values()->toArray(),
        ], 409);
    }
}
