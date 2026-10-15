<?php

namespace LaraGram\Ai\Events;

use LaraGram\Ai\Prompts\ImagePrompt;
use LaraGram\Ai\Providers\Provider;
use LaraGram\Ai\Responses\ImageResponse;

class ImageGenerated
{
    public function __construct(
        public string $invocationId,
        public Provider $provider,
        public string $model,
        public ImagePrompt $prompt,
        public ImageResponse $response,
    ) {}
}
