<?php

namespace LaraGram\Ai\Events;

use LaraGram\Ai\Prompts\RerankingPrompt;
use LaraGram\Ai\Providers\Provider;

class Reranking
{
    public function __construct(
        public string $invocationId,
        public Provider $provider,
        public string $model,
        public RerankingPrompt $prompt,
    ) {}
}
