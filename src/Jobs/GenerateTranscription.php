<?php

namespace LaraGram\Ai\Jobs;

use LaraGram\Contracts\Queue\ShouldQueue;
use LaraGram\Foundation\Queue\Queueable;
use LaraGram\Ai\Enums\Lab;
use LaraGram\Ai\PendingResponses\PendingTranscriptionGeneration;
use LaraGram\Ai\Responses\TranscriptionResponse;

class GenerateTranscription implements ShouldQueue
{
    use Concerns\InvokesQueuedResponseCallbacks;
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public PendingTranscriptionGeneration $pendingTranscription,
        public Lab|array|string|null $provider = null,
        public ?string $model = null) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->withCallbacks(fn (): TranscriptionResponse => $this->pendingTranscription->generate(
            $this->provider,
            $this->model,
        ));
    }
}
