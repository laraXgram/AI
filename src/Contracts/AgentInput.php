<?php

namespace LaraGram\Ai\Contracts;

use LaraGram\Ai\Approvals\Decisions;
use LaraGram\Ai\Messages\UserMessage;

interface AgentInput
{
    /**
     * Get the newest user message, if the input contains one.
     */
    public function message(): ?UserMessage;

    /**
     * Get the tool approval decisions, if the input contains any.
     */
    public function decisions(): ?Decisions;
}
