<?php

namespace LaraGram\Ai\Files;

use LaraGram\Contracts\Support\Arrayable;
use InvalidArgumentException;
use JsonSerializable;
use LaraGram\Ai\Contracts\Files\StorableFile;
use LaraGram\Ai\Contracts\Files\TranscribableAudio;
use LaraGram\Ai\Files\Concerns\CanBeUploadedToProvider;
use LaraGram\Ai\Files\Concerns\HasLocalContent;
use LaraGram\Ai\PendingResponses\PendingTranscriptionGeneration;
use LaraGram\Ai\Transcription;

class LocalAudio extends Audio implements Arrayable, JsonSerializable, StorableFile, TranscribableAudio
{
    use CanBeUploadedToProvider;
    use HasLocalContent;

    public function __construct(public string $path, ?string $mimeType = null)
    {
        if (blank($path)) {
            throw new InvalidArgumentException('Audio file path cannot be empty.');
        }

        $this->mime = $mimeType;
    }

    /**
     * Generate a transcription of the given audio.
     */
    public function transcription(): PendingTranscriptionGeneration
    {
        return Transcription::of($this);
    }

    /**
     * Get the instance as an array.
     */
    public function toArray(): array
    {
        return [
            'type' => 'local-audio',
            'name' => $this->name(),
            'path' => $this->path,
            'mime' => $this->mime,
        ];
    }
}
