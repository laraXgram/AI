<?php

namespace LaraGram\Ai;

use LaraGram\Ai\Files\TelegramFile;
use Closure;
use InvalidArgumentException;
use LaraGram\Ai\Files\Audio;
use LaraGram\Ai\Files\Document;
use LaraGram\Ai\Files\Image;
use LaraGram\Ai\Files\Video;
use LaraGram\Ai\Gateway\FakeEmbeddingGateway;
use LaraGram\Ai\PendingResponses\PendingEmbeddingsGeneration;

class Embeddings
{
    /**
     * Get embedding vectors representing the given inputs.
     *
     * @param  array<int, string|Audio|Document|Image|Video>  $inputs
     *
     * @throws InvalidArgumentException if the given inputs are not a list, are empty, or contain an unsupported input type.
     */
    public static function for(array $inputs): PendingEmbeddingsGeneration
    {
        return new PendingEmbeddingsGeneration(TelegramFile::normalize($inputs));
    }

    /**
     * Fake embeddings generation.
     */
    public static function fake(Closure|array $responses = []): FakeEmbeddingGateway
    {
        return Ai::fakeEmbeddings($responses);
    }

    /**
     * Determine if embeddings generation is faked.
     */
    public static function isFaked(): bool
    {
        return Ai::embeddingsAreFaked();
    }

    /**
     * Generate a fake embedding vector of the given dimensions.
     *
     * @return array<float>
     */
    public static function fakeEmbedding(int $dimensions): array
    {
        // Generate random values...
        $values = array_map(
            fn (): float|int => (mt_rand() / mt_getrandmax()) * 2 - 1,
            range(1, $dimensions)
        );

        // Normalize the vector (unit length)...
        $magnitude = sqrt(array_sum(array_map(fn ($v): int|float => $v * $v, $values)));

        return array_map(fn ($v): float => $v / $magnitude, $values);
    }
}
