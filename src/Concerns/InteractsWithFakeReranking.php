<?php

namespace LaraGram\Ai\Concerns;

use Closure;
use LaraGram\Support\Collection;
use LaraGram\Ai\Gateway\FakeRerankingGateway;
use LaraGram\Ai\Prompts\RerankingPrompt;

trait InteractsWithFakeReranking
{
    /**
     * The fake reranking gateway instance.
     */
    protected ?FakeRerankingGateway $fakeRerankingGateway = null;

    /**
     * All of the recorded rerankings.
     */
    protected array $recordedRerankings = [];

    /**
     * Fake reranking operations.
     */
    public function fakeReranking(Closure|array $responses = []): FakeRerankingGateway
    {
        return $this->fakeRerankingGateway = new FakeRerankingGateway($responses);
    }

    /**
     * Record a reranking.
     */
    public function recordReranking(RerankingPrompt $prompt): self
    {
        $this->recordedRerankings[] = $prompt;

        return $this;
    }

    /**
     * Determine if reranking is faked.
     */
    public function rerankingIsFaked(): bool
    {
        return $this->fakeRerankingGateway !== null;
    }

    /**
     * Get the fake reranking gateway.
     */
    public function fakeRerankingGateway(): ?FakeRerankingGateway
    {
        return $this->fakeRerankingGateway;
    }
}
