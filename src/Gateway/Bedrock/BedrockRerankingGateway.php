<?php

namespace LaraGram\Ai\Gateway\Bedrock;

use LaraGram\Support\Collection;
use LaraGram\Ai\Contracts\Gateway\RerankingGateway;
use LaraGram\Ai\Contracts\Providers\RerankingProvider;
use LaraGram\Ai\Gateway\Bedrock\Concerns\CreatesBedrockClient;
use LaraGram\Ai\Gateway\Concerns\HandlesFailoverErrors;
use LaraGram\Ai\Responses\Data\Meta;
use LaraGram\Ai\Responses\Data\RankedDocument;
use LaraGram\Ai\Responses\Data\RerankingUsage;
use LaraGram\Ai\Responses\RerankingResponse;
use Throwable;

class BedrockRerankingGateway implements RerankingGateway
{
    use CreatesBedrockClient;
    use HandlesFailoverErrors;

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
        array $providerOptions = []
    ): RerankingResponse {
        $client = $this->createBedrockClient($provider, $timeout);

        try {
            $response = $this->withErrorHandling(
                $provider->name(),
                fn () => $client->invokeModel([
                    'modelId' => $model,
                    'contentType' => 'application/json',
                    'accept' => 'application/json',
                    'body' => json_encode(array_merge($providerOptions, array_filter([
                        'query' => $query,
                        'documents' => array_values($documents),
                        'top_n' => $limit,
                        'api_version' => str_starts_with($model, 'cohere.') ? 2 : null,
                    ]))),
                ]),
            );

            $data = json_decode((string) $response->get('body')->getContents(), true);
        } catch (Throwable $throwable) {
            throw BedrockException::toAiException($throwable, $provider->name(), $model);
        }

        $results = (new Collection($data['results'] ?? []))->map(fn (array $result): RankedDocument => new RankedDocument(
            index: $result['index'],
            document: $documents[$result['index']],
            score: $result['relevance_score'],
        ))->all();

        return new RerankingResponse(
            $results,
            new RerankingUsage,
            new Meta($provider->name(), $model),
        );
    }
}
