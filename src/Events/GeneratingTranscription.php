<?php

namespace LaraGram\Ai\Events;

use LaraGram\Ai\Prompts\TranscriptionPrompt;
use LaraGram\Ai\Providers\Provider;

class GeneratingTranscription
{
    public function __construct(
        public string $invocationId,
        public Provider $provider,
        public string $model,
        public TranscriptionPrompt $prompt,
    ) {}
}
