<?php

namespace LaraGram\Ai\Concerns;

use Closure;
use LaraGram\Support\Collection;
use LaraGram\Ai\Gateway\FakeEmbeddingGateway;
use LaraGram\Ai\Prompts\EmbeddingsPrompt;
use LaraGram\Ai\Prompts\QueuedEmbeddingsPrompt;

trait InteractsWithFakeEmbeddings
{
    /**
     * The fake embedding gateway instance.
     */
    protected ?FakeEmbeddingGateway $fakeEmbeddingGateway = null;

    /**
     * All of the recorded embeddings generations.
     */
    protected array $recordedEmbeddingsGenerations = [];

    /**
     * All of the recorded embeddings generations that were queued.
     */
    protected array $recordedQueuedEmbeddingsGenerations = [];

    /**
     * Fake embeddings generation.
     */
    public function fakeEmbeddings(Closure|array $responses = []): FakeEmbeddingGateway
    {
        return $this->fakeEmbeddingGateway = new FakeEmbeddingGateway($responses);
    }

    /**
     * Record an embeddings generation.
     */
    public function recordEmbeddingsGeneration(EmbeddingsPrompt|QueuedEmbeddingsPrompt $prompt): self
    {
        if ($prompt instanceof QueuedEmbeddingsPrompt) {
            $this->recordedQueuedEmbeddingsGenerations[] = $prompt;
        } else {
            $this->recordedEmbeddingsGenerations[] = $prompt;
        }

        return $this;
    }

    /**
     * Determine if embeddings generation is faked.
     */
    public function embeddingsAreFaked(): bool
    {
        return $this->fakeEmbeddingGateway !== null;
    }

    /**
     * Get the fake embedding gateway.
     */
    public function fakeEmbeddingGateway(): ?FakeEmbeddingGateway
    {
        return $this->fakeEmbeddingGateway;
    }
}
