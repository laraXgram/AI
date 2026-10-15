<?php

namespace LaraGram\Ai\Events;

use LaraGram\Ai\Prompts\AgentPrompt;
use LaraGram\Ai\Responses\AgentResponse;
use LaraGram\Ai\Responses\StreamedAgentResponse;

class AgentPrompted
{
    public function __construct(
        public string $invocationId,
        public AgentPrompt $prompt,
        public StreamedAgentResponse|AgentResponse $response
    ) {}
}
