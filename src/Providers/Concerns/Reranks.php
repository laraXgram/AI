<?php

namespace LaraGram\Ai\Providers\Concerns;

use LaraGram\Support\Str;
use LaraGram\Ai\Ai;
use LaraGram\Ai\Events\Reranked;
use LaraGram\Ai\Events\Reranking;
use LaraGram\Ai\Events\RerankingFailed;
use LaraGram\Ai\Prompts\RerankingPrompt;
use LaraGram\Ai\Responses\RerankingResponse;
use Throwable;

trait Reranks
{
    /**
     * Rerank the given documents based on their relevance to the query.
     *
     * @param  array<int, string>  $documents
     * @param  array<string, mixed>  $providerOptions
     */
    public function rerank(array $documents, string $query, ?int $limit = null, ?string $model = null, int $timeout = 30, array $providerOptions = []): RerankingResponse
    {
        $invocationId = (string) Str::uuid7();

        $model ??= $this->defaultRerankingModel();

        $prompt = new RerankingPrompt($documents, $query, $limit, $this, $model, $timeout, $providerOptions);

        if (Ai::rerankingIsFaked()) {
            Ai::recordReranking($prompt);
        }

        $this->events->dispatch(new Reranking(
            $invocationId, $this, $model, $prompt,
        ));

        return tap($this->dispatchingFailure(
            fn () => $this->rerankingGateway()->rerank(
                $this,
                $model,
                $documents,
                $query,
                $limit,
                $timeout,
                $providerOptions,
            ),
            fn (Throwable $exception) => new RerankingFailed($invocationId, $this, $model, $prompt, $exception),
        ), fn (RerankingResponse $response) => $this->events->dispatch(new Reranked(
            $invocationId, $this, $model, $prompt, $response,
        )));
    }
}
