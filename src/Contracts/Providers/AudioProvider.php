<?php

namespace LaraGram\Ai\Contracts\Providers;

use LaraGram\Ai\Contracts\Gateway\AudioGateway;
use LaraGram\Ai\Responses\AudioResponse;

interface AudioProvider extends Provider
{
    /**
     * Generate audio from the given text.
     *
     * @param  array<string, mixed>  $providerOptions
     */
    public function audio(
        string $text,
        string $voice = 'default-female',
        ?string $instructions = null,
        ?string $model = null,
        int $timeout = 30,
        array $providerOptions = [],
    ): AudioResponse;

    /**
     * Get the provider's audio gateway.
     */
    public function audioGateway(): AudioGateway;

    /**
     * Set the provider's audio gateway.
     */
    public function useAudioGateway(AudioGateway $gateway): self;

    /**
     * Get the name of the default audio (TTS) model.
     */
    public function defaultAudioModel(): string;
}
