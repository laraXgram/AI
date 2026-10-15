<?php

namespace LaraGram\Ai\Files;

use LaraGram\Contracts\Support\Arrayable;
use LaraGram\Http\UploadedFile;
use InvalidArgumentException;
use JsonSerializable;
use LaraGram\Ai\Contracts\Files\StorableFile;
use LaraGram\Ai\Files\Concerns\CanBeUploadedToProvider;

class Base64Document extends Document implements Arrayable, JsonSerializable, StorableFile
{
    use CanBeUploadedToProvider;

    public function __construct(public string $base64, ?string $mimeType = null)
    {
        if (blank($base64)) {
            throw new InvalidArgumentException('Base64 document content cannot be empty.');
        }

        $this->mime = $mimeType;
    }

    /**
     * Create a new instance from an uploaded file.
     */
    #[\Override]
    public static function fromUpload(mixed $file, ?string $mimeType = null): self
    {
        if (TelegramFile::is($file)) {
            $attachment = TelegramFile::toFile($file);

            return (new self($attachment->base64, $mimeType ?? $attachment->mimeType()))->as($attachment->name());
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
     * Get the instance as an array.
     */
    public function toArray(): array
    {
        return [
            'type' => 'base64-document',
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
