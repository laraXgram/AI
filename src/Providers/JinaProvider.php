<?php

namespace LaraGram\Ai\Providers;

use LaraGram\Contracts\Events\Dispatcher;
use LaraGram\Ai\Contracts\Gateway\EmbeddingGateway;
use LaraGram\Ai\Contracts\Gateway\RerankingGateway;
use LaraGram\Ai\Contracts\Providers\EmbeddingProvider;
use LaraGram\Ai\Contracts\Providers\RerankingProvider;
use LaraGram\Ai\Gateway\JinaGateway;

class JinaProvider extends Provider implements EmbeddingProvider, RerankingProvider
{
    use Concerns\GeneratesEmbeddings;
    use Concerns\HasEmbeddingGateway;
    use Concerns\HasRerankingGateway;
    use Concerns\Reranks;

    public function __construct(
        protected array $config,
        protected Dispatcher $events,
    ) {}

    /**
     * Get the name of the default embeddings model.
     */
    public function defaultEmbeddingsModel(): string
    {
        return $this->config['models']['embeddings']['default'] ?? 'jina-embeddings-v4';
    }

    /**
     * Get the default dimensions of the default embeddings model.
     */
    public function defaultEmbeddingsDimensions(): int
    {
        return $this->config['models']['embeddings']['dimensions'] ?? 2048;
    }

    /**
     * Get the provider's embedding gateway.
     */
    public function embeddingGateway(): EmbeddingGateway
    {
        return $this->embeddingGateway ??= new JinaGateway;
    }

    /**
     * Get the name of the default reranking model.
     */
    public function defaultRerankingModel(): string
    {
        return $this->config['models']['reranking']['default'] ?? 'jina-reranker-v3.5';
    }

    /**
     * Get the provider's reranking gateway.
     */
    public function rerankingGateway(): RerankingGateway
    {
        return $this->rerankingGateway ??= new JinaGateway;
    }
}
