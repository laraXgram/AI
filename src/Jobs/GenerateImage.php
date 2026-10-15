<?php

namespace LaraGram\Ai\Jobs;

use LaraGram\Contracts\Queue\ShouldQueue;
use LaraGram\Foundation\Queue\Queueable;
use LaraGram\Ai\Enums\Lab;
use LaraGram\Ai\PendingResponses\PendingImageGeneration;
use LaraGram\Ai\Responses\ImageResponse;

class GenerateImage implements ShouldQueue
{
    use Concerns\InvokesQueuedResponseCallbacks;
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public PendingImageGeneration $pendingImage,
        public Lab|array|string|null $provider = null,
        public ?string $model = null) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->withCallbacks(fn (): ImageResponse => $this->pendingImage->generate(
            $this->provider,
            $this->model,
        ));
    }
}
