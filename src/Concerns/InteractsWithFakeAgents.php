<?php

namespace LaraGram\Ai\Concerns;

use Closure;
use LaraGram\Support\Collection;
use LaraGram\Support\Str;
use InvalidArgumentException;
use LaraGram\Ai\Contracts\Agent;
use LaraGram\Ai\Gateway\FakeTextGateway;
use LaraGram\Ai\Prompts\AgentPrompt;
use LaraGram\Ai\QueuedAgentPrompt;

trait InteractsWithFakeAgents
{
    /**
     * All of the registered fake agent gateways.
     */
    protected array $fakeAgentGateways = [];

    /**
     * All of the recorded agent prompts.
     */
    protected array $recordedPrompts = [];

    /**
     * All of the recorded agent prompts that were queued.
     */
    protected array $recordedQueuedPrompts = [];

    /**
     * Fake the responses returned by the given agent.
     */
    public function fakeAgent(string $agent, Closure|array $responses = []): FakeTextGateway
    {
        return tap(
            new FakeTextGateway($responses),
            fn ($gateway) => $this->fakeAgentGateways[$agent] = $gateway
        );
    }

    /**
     * Determine if the given agent has been faked.
     */
    public function hasFakeGatewayFor(Agent|string $agent): bool
    {
        return array_key_exists(
            is_object($agent) ? $agent::class : $agent,
            $this->fakeAgentGateways
        );
    }

    /**
     * Get a fake gateway instance for the given agent.
     */
    public function fakeGatewayFor(Agent $agent): FakeTextGateway
    {
        return $this->hasFakeGatewayFor($agent)
            ? $this->fakeAgentGateways[$agent::class]
            : throw new InvalidArgumentException('Agent ['.$agent::class.'] has not been faked.');
    }

    /**
     * Record the given prompt for the faked agent.
     */
    public function recordPrompt(AgentPrompt|QueuedAgentPrompt $prompt): self
    {
        if ($prompt instanceof QueuedAgentPrompt) {
            $this->recordedQueuedPrompts[$prompt->agent::class][] = $prompt;
        } else {
            $this->recordedPrompts[$prompt->agent::class][] = $prompt;
        }

        return $this;
    }
}
