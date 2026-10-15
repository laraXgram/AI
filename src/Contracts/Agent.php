<?php

namespace LaraGram\Ai\Contracts;

use LaraGram\Broadcasting\Channel;
use LaraGram\Ai\Approvals\Decisions;
use LaraGram\Ai\Enums\Lab;
use LaraGram\Ai\Messages\UserMessage;
use LaraGram\Ai\Responses\AgentResponse;
use LaraGram\Ai\Responses\QueuedAgentResponse;
use LaraGram\Ai\Responses\StreamableAgentResponse;
use Stringable;

interface Agent
{
    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string;

    /**
     * Invoke the agent with a given prompt, or resume a paused run with tool approval decisions.
     */
    public function prompt(
        AgentInput|UserMessage|Decisions|string $prompt,
        array $attachments = [],
        Lab|array|string|null $provider = null,
        ?string $model = null,
        ?int $timeout = null,
    ): AgentResponse;

    /**
     * Invoke the agent with a given prompt and return a streamable response.
     */
    public function stream(
        AgentInput|UserMessage|Decisions|string $prompt,
        array $attachments = [],
        Lab|array|string|null $provider = null,
        ?string $model = null,
        ?int $timeout = null,
    ): StreamableAgentResponse;

    /**
     * Invoke the agent in a queued job.
     */
    public function queue(
        AgentInput|UserMessage|Decisions|string $prompt,
        array $attachments = [],
        Lab|array|string|null $provider = null,
        ?string $model = null
    ): QueuedAgentResponse;

    /**
     * Invoke the agent with a given prompt and broadcast the streamed events.
     */
    public function broadcast(
        AgentInput|UserMessage|Decisions|string $prompt,
        Channel|array $channels,
        array $attachments = [],
        bool $now = false,
        Lab|array|string|null $provider = null,
        ?string $model = null
    ): StreamableAgentResponse;

    /**
     * Invoke the agent with a given prompt and broadcast the streamed events immediately.
     */
    public function broadcastNow(
        AgentInput|UserMessage|Decisions|string $prompt,
        Channel|array $channels,
        array $attachments = [],
        Lab|array|string|null $provider = null,
        ?string $model = null
    ): StreamableAgentResponse;

    /**
     * Queue the agent with a given prompt and broadcast the streamed events.
     */
    public function broadcastOnQueue(
        AgentInput|UserMessage|Decisions|string $prompt,
        Channel|array $channels,
        array $attachments = [],
        Lab|array|string|null $provider = null,
        ?string $model = null
    ): QueuedAgentResponse;
}
