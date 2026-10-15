<?php

namespace LaraGram\Ai\Events;

use LaraGram\Ai\Prompts\EmbeddingsPrompt;
use LaraGram\Ai\Providers\Provider;

class GeneratingEmbeddings
{
    public function __construct(
        public string $invocationId,
        public Provider $provider,
        public string $model,
        public EmbeddingsPrompt $prompt,
    ) {}
}
