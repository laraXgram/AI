<?php

namespace LaraGram\Ai\Jobs;

use LaraGram\Contracts\Queue\ShouldQueue;
use LaraGram\Foundation\Queue\Queueable;
use LaraGram\Ai\Approvals\Decisions;
use LaraGram\Ai\Contracts\Agent;
use LaraGram\Ai\Enums\Lab;
use LaraGram\Ai\Responses\AgentResponse;

class InvokeAgent implements ShouldQueue
{
    use Concerns\InvokesQueuedResponseCallbacks;
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Agent $agent,
        public Decisions|string $prompt = '',
        public array $attachments = [],
        public Lab|array|string|null $provider = null,
        public ?string $model = null) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->withCallbacks(fn (): AgentResponse => $this->agent->prompt(
            $this->prompt, $this->attachments, $this->provider, $this->model
        ));
    }

    /**
     * Get the display name for the queued job.
     */
    public function displayName(): string
    {
        return $this->agent::class;
    }
}
