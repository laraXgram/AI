<?php

namespace LaraGram\Ai\Files;

use LaraGram\Contracts\Support\Arrayable;
use LaraGram\Http\UploadedFile;
use InvalidArgumentException;
use JsonSerializable;
use LaraGram\Ai\Contracts\Files\StorableFile;
use LaraGram\Ai\Contracts\Files\TranscribableAudio;
use LaraGram\Ai\Files\Concerns\CanBeUploadedToProvider;
use LaraGram\Ai\PendingResponses\PendingTranscriptionGeneration;
use LaraGram\Ai\Transcription;

class Base64Audio extends Audio implements Arrayable, JsonSerializable, StorableFile, TranscribableAudio
{
    use CanBeUploadedToProvider;

    public function __construct(public string $base64, ?string $mimeType = null)
    {
        if (blank($base64)) {
            throw new InvalidArgumentException('Base64 audio content cannot be empty.');
        }

        $this->mime = $mimeType;
    }

    /**
     * Create a new instance from an uploaded file.
     */
    public static function fromUpload(mixed $file, ?string $mimeType = null): self
    {
        if (TelegramFile::is($file)) {
            $attachment = TelegramFile::toFile($file);

            return new self($attachment->base64, $mimeType ?? $attachment->mimeType());
        }

        return new self(
            base64_encode($file->getContent()),
            mimeType: $mimeType ?? $file->getClientMimeType(),
        );
    }

    /**
     * Get the raw representation of the file.
     */
    public function content(): string
    {
        return base64_decode($this->base64);
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
            'type' => 'base64-audio',
            'name' => $this->name(),
            'base64' => $this->base64,
            'mime' => $this->mime,
        ];
    }

    /**
     * Get the JSON serializable representation of the instance.
     */
    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }

    public function __toString(): string
    {
        return $this->content();
    }
}
