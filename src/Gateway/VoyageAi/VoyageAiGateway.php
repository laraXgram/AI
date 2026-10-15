<?php

namespace LaraGram\Ai\Gateway\VoyageAi;

use LaraGram\Support\Collection;
use LaraGram\Ai\Contracts\Gateway\EmbeddingGateway;
use LaraGram\Ai\Contracts\Gateway\RerankingGateway;
use LaraGram\Ai\Contracts\Providers\EmbeddingProvider;
use LaraGram\Ai\Contracts\Providers\RerankingProvider;
use LaraGram\Ai\Gateway\Concerns\HandlesFailoverErrors;
use LaraGram\Ai\Responses\Data\Meta;
use LaraGram\Ai\Responses\Data\RankedDocument;
use LaraGram\Ai\Responses\Data\RerankingUsage;
use LaraGram\Ai\Responses\Data\Usage;
use LaraGram\Ai\Responses\EmbeddingsResponse;
use LaraGram\Ai\Responses\RerankingResponse;

class VoyageAiGateway implements EmbeddingGateway, RerankingGateway
{
    use Concerns\CreatesVoyageAiClient;
    use Concerns\MapsEmbeddingInputs;
    use HandlesFailoverErrors;

    /**
     * {@inheritdoc}
     */
    public function generateEmbeddings(
        EmbeddingProvider $provider,
        string $model,
        array $inputs,
        int $dimensions,
        int $timeout = 30,
        array $providerOptions = [],
    ): EmbeddingsResponse {
        if ($this->usesMultimodalEmbeddingEndpoint($model, $inputs)) {
            return $this->generateMultimodalEmbeddings($provider, $model, $inputs, $dimensions, $timeout, $providerOptions);
        }

        $data = $this->withErrorHandling(
            $provider->name(),
            fn () => $this->client($provider, $timeout)->post('/embeddings', array_merge($providerOptions, [
                'model' => $model,
                'input' => $inputs,
                'output_dimension' => $dimensions,
            ])),
        )->json();

        return new EmbeddingsResponse(
            (new Collection($data['data']))->pluck('embedding')->all(),
            new Usage($data['usage']['total_tokens'] ?? 0),
            new Meta($provider->name(), $model),
        );
    }

    /**
     * Rerank the given documents based on their relevance to the query.
     *
     * @param  array<int, string>  $documents
     * @param  array<string, mixed>  $providerOptions
     */
    public function rerank(
        RerankingProvider $provider,
        string $model,
        array $documents,
        string $query,
        ?int $limit = null,
        int $timeout = 30,
        array $providerOptions = [],
    ): RerankingResponse {
        $data = $this->withErrorHandling(
            $provider->name(),
            fn () => $this->client($provider, $timeout)->post('/rerank', array_merge($providerOptions, array_filter([
                'model' => $model,
                'query' => $query,
                'documents' => $documents,
                'top_k' => $limit,
            ]))),
        )->json();

        return new RerankingResponse(
            collect($data['data'])->map(fn (array $result): RankedDocument => new RankedDocument(
                index: $result['index'],
                document: $documents[$result['index']],
                score: $result['relevance_score'],
            ))->all(),
            new RerankingUsage($data['usage']['total_tokens'] ?? 0),
            new Meta($provider->name(), $model),
        );
    }
}
