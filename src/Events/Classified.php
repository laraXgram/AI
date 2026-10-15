<?php

namespace LaraGram\Ai\Events;

use LaraGram\Ai\Prompts\ClassificationPrompt;
use LaraGram\Ai\Providers\Provider;
use LaraGram\Ai\Responses\ClassificationResponse;

class Classified
{
    public function __construct(
        public string $invocationId,
        public Provider $provider,
        public string $model,
        public ClassificationPrompt $prompt,
        public ClassificationResponse $response,
    ) {}
}
