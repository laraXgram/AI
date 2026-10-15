<?php

namespace LaraGram\Ai\Contracts\Files;

use LaraGram\Ai\PendingResponses\PendingTranscriptionGeneration;
use Stringable;

interface TranscribableAudio extends HasContent, HasMimeType, Stringable
{
    /**
     * Generate a transcription of the given audio.
     */
    public function transcription(): PendingTranscriptionGeneration;
}
