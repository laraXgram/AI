<?php

namespace LaraGram\Ai;

use Closure;
use InvalidArgumentException;
use LaraGram\Ai\Gateway\FakeImageGateway;
use LaraGram\Ai\PendingResponses\PendingImageGeneration;

class Image
{
    /**
     * Generate an image.
     *
     * @throws InvalidArgumentException if the given prompt is empty or whitespace-only.
     */
    public static function of(string $prompt): PendingImageGeneration
    {
        return new PendingImageGeneration($prompt);
    }

    /**
     * Fake image generation.
     */
    public static function fake(Closure|array $responses = []): FakeImageGateway
    {
        return Ai::fakeImages($responses);
    }

    /**
     * Determine if image generation is faked.
     */
    public static function isFaked(): bool
    {
        return Ai::imagesAreFaked();
    }
}
