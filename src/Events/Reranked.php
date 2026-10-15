<?php

namespace LaraGram\Ai\Events;

use LaraGram\Ai\Prompts\RerankingPrompt;
use LaraGram\Ai\Providers\Provider;
use LaraGram\Ai\Responses\RerankingResponse;

class Reranked
{
    public function __construct(
        public string $invocationId,
        public Provider $provider,
        public string $model,
        public RerankingPrompt $prompt,
        public RerankingResponse $response,
    ) {}
}
