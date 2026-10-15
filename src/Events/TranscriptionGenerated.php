<?php

namespace LaraGram\Ai\Events;

use LaraGram\Ai\Prompts\TranscriptionPrompt;
use LaraGram\Ai\Providers\Provider;
use LaraGram\Ai\Responses\TranscriptionResponse;

class TranscriptionGenerated
{
    public function __construct(
        public string $invocationId,
        public Provider $provider,
        public string $model,
        public TranscriptionPrompt $prompt,
        public TranscriptionResponse $response,
    ) {}
}
