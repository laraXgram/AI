<?php

namespace LaraGram\Ai;

use Closure;
use InvalidArgumentException;
use LaraGram\Ai\Gateway\FakeAudioGateway;
use LaraGram\Ai\PendingResponses\PendingAudioGeneration;

class Audio
{
    /**
     * Generate audio from the given text.
     *
     * @throws InvalidArgumentException if the given text is empty or whitespace-only.
     */
    public static function of(string $text): PendingAudioGeneration
    {
        return new PendingAudioGeneration($text);
    }

    /**
     * Fake audio generation.
     */
    public static function fake(Closure|array $responses = []): FakeAudioGateway
    {
        return Ai::fakeAudio($responses);
    }

    /**
     * Determine if audio generation is faked.
     */
    public static function isFaked(): bool
    {
        return Ai::audioIsFaked();
    }
}
