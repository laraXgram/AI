<?php

namespace LaraGram\Ai\Contracts\Gateway;

use LaraGram\Ai\Contracts\Providers\RerankingProvider;
use LaraGram\Ai\Responses\RerankingResponse;

interface RerankingGateway
{
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
    ): RerankingResponse;
}
