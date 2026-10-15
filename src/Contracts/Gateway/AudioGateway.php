<?php

namespace LaraGram\Ai\Contracts\Gateway;

use LaraGram\Ai\Contracts\Providers\AudioProvider;
use LaraGram\Ai\Responses\AudioResponse;

interface AudioGateway
{
    /**
     * Generate audio from the given text.
     *
     * @param  array<string, mixed>  $providerOptions
     */
    public function generateAudio(
        AudioProvider $provider,
        string $model,
        string $text,
        string $voice,
        ?string $instructions = null,
        int $timeout = 30,
        array $providerOptions = [],
    ): AudioResponse;
}
