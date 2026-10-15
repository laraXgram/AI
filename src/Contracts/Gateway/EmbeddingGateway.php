<?php

namespace LaraGram\Ai\Contracts\Gateway;

use LaraGram\Ai\Contracts\Providers\EmbeddingProvider;
use LaraGram\Ai\Files\Audio;
use LaraGram\Ai\Files\Document;
use LaraGram\Ai\Files\Image;
use LaraGram\Ai\Files\Video;
use LaraGram\Ai\Responses\EmbeddingsResponse;

interface EmbeddingGateway
{
    /**
     * Generate embedding vectors representing the given inputs.
     *
     * @param  array<int, string|Audio|Document|Image|Video>  $inputs
     * @param  array<string, mixed>  $providerOptions
     */
    public function generateEmbeddings(EmbeddingProvider $provider, string $model, array $inputs, int $dimensions, int $timeout = 30, array $providerOptions = []): EmbeddingsResponse;
}
