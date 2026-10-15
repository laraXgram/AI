<?php

namespace LaraGram\Ai\Events;

use LaraGram\Ai\Prompts\ClassificationPrompt;
use LaraGram\Ai\Providers\Provider;

class Classifying
{
    public function __construct(
        public string $invocationId,
        public Provider $provider,
        public string $model,
        public ClassificationPrompt $prompt,
    ) {}
}
