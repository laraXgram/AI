<?php

namespace LaraGram\Ai\Concerns;

use Closure;
use LaraGram\Support\Collection;
use LaraGram\Ai\Gateway\FakeImageGateway;
use LaraGram\Ai\Prompts\ImagePrompt;
use LaraGram\Ai\Prompts\QueuedImagePrompt;

trait InteractsWithFakeImages
{
    /**
     * The fake image gateway instance.
     */
    protected ?FakeImageGateway $fakeImageGateway = null;

    /**
     * All of the recorded image generations.
     */
    protected array $recordedImageGenerations = [];

    /**
     * All of the recorded image generations that were queued.
     */
    protected array $recordedQueuedImageGenerations = [];

    /**
     * Fake image generation.
     */
    public function fakeImages(Closure|array $responses = []): FakeImageGateway
    {
        return $this->fakeImageGateway = new FakeImageGateway($responses);
    }

    /**
     * Record an image generation.
     */
    public function recordImageGeneration(ImagePrompt|QueuedImagePrompt $prompt): self
    {
        if ($prompt instanceof QueuedImagePrompt) {
            $this->recordedQueuedImageGenerations[] = $prompt;
        } else {
            $this->recordedImageGenerations[] = $prompt;
        }

        return $this;
    }

    /**
     * Determine if image generation is faked.
     */
    public function imagesAreFaked(): bool
    {
        return $this->fakeImageGateway !== null;
    }

    /**
     * Get the fake image gateway.
     */
    public function fakeImageGateway(): ?FakeImageGateway
    {
        return $this->fakeImageGateway;
    }
}
