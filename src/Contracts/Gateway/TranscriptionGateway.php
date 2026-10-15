<?php

namespace LaraGram\Ai\Contracts\Gateway;

use LaraGram\Ai\Contracts\Files\TranscribableAudio;
use LaraGram\Ai\Contracts\Providers\TranscriptionProvider;
use LaraGram\Ai\Responses\TranscriptionResponse;

interface TranscriptionGateway
{
    /**
     * Generate text from the given audio.
     *
     * @param  array<string, mixed>  $providerOptions
     */
    public function generateTranscription(
        TranscriptionProvider $provider,
        string $model,
        TranscribableAudio $audio,
        ?string $language = null,
        bool $diarize = false,
        int $timeout = 30,
        array $providerOptions = [],
    ): TranscriptionResponse;
}
