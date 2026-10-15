<?php

namespace LaraGram\Ai\Gateway;

use LaraGram\Ai\Messages\Message;
use LaraGram\Ai\Responses\Data\ToolResult;

class ApprovalResumption
{
    /**
     * @param  Message[]  $messages
     * @param  Message[]  $newMessages
     * @param  array<int, ToolResult>  $results
     */
    public function __construct(
        public array $messages,
        public array $newMessages,
        public array $results,
        public bool $shouldContinue,
    ) {}
}
