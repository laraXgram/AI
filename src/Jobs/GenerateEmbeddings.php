<?php

namespace LaraGram\Ai\Jobs;

use LaraGram\Contracts\Queue\ShouldQueue;
use LaraGram\Foundation\Queue\Queueable;
use LaraGram\Ai\Enums\Lab;
use LaraGram\Ai\PendingResponses\PendingEmbeddingsGeneration;
use LaraGram\Ai\Responses\EmbeddingsResponse;

class GenerateEmbeddings implements ShouldQueue
{
    use Concerns\InvokesQueuedResponseCallbacks;
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public PendingEmbeddingsGeneration $pendingEmbeddings,
        public Lab|array|string|null $provider = null,
        public ?string $model = null) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->withCallbacks(fn (): EmbeddingsResponse => $this->pendingEmbeddings->generate(
            $this->provider,
            $this->model,
        ));
    }
}
