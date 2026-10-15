<?php

namespace LaraGram\Ai\Events;

use LaraGram\Ai\Prompts\AgentPrompt;

class PromptingAgent
{
    public function __construct(
        public string $invocationId,
        public AgentPrompt $prompt,
    ) {}
}
