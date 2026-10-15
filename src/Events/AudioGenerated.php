<?php

namespace LaraGram\Ai\Events;

use LaraGram\Ai\Prompts\AudioPrompt;
use LaraGram\Ai\Providers\Provider;
use LaraGram\Ai\Responses\AudioResponse;

class AudioGenerated
{
    public function __construct(
        public string $invocationId,
        public Provider $provider,
        public string $model,
        public AudioPrompt $prompt,
        public AudioResponse $response,
    ) {}
}
