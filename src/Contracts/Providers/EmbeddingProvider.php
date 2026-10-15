<?php

namespace LaraGram\Ai\Contracts\Providers;

use LaraGram\Ai\Contracts\Gateway\EmbeddingGateway;
use LaraGram\Ai\Files\Audio;
use LaraGram\Ai\Files\Document;
use LaraGram\Ai\Files\Image;
use LaraGram\Ai\Files\Video;
use LaraGram\Ai\Responses\EmbeddingsResponse;

interface EmbeddingProvider extends Provider
{
    /**
     * Get embedding vectors representing the given inputs.
     *
     * @param  array<int, string|Audio|Document|Image|Video>  $inputs
     * @param  array<string, mixed>  $providerOptions
     */
    public function embeddings(array $inputs, ?int $dimensions = null, ?string $model = null, int $timeout = 30, array $providerOptions = []): EmbeddingsResponse;

    /**
     * Get the provider's embedding gateway.
     */
    public function embeddingGateway(): EmbeddingGateway;

    /**
     * Set the provider's embedding gateway.
     */
    public function useEmbeddingGateway(EmbeddingGateway $gateway): self;

    /**
     * Get the name of the default embeddings model.
     */
    public function defaultEmbeddingsModel(): string;

    /**
     * Get the default dimensions of the default embeddings model.
     */
    public function defaultEmbeddingsDimensions(): int;
}
