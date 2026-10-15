<?php

namespace LaraGram\Ai\Jobs;

use LaraGram\Contracts\Queue\ShouldQueue;
use LaraGram\Foundation\Queue\Queueable;
use LaraGram\Ai\Enums\Lab;
use LaraGram\Ai\PendingResponses\PendingAudioGeneration;
use LaraGram\Ai\Responses\AudioResponse;

class GenerateAudio implements ShouldQueue
{
    use Concerns\InvokesQueuedResponseCallbacks;
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public PendingAudioGeneration $pendingAudio,
        public Lab|array|string|null $provider = null,
        public ?string $model = null) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->withCallbacks(fn (): AudioResponse => $this->pendingAudio->generate(
            $this->provider,
            $this->model,
        ));
    }
}
