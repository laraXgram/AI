<?php

namespace LaraGram\Ai\Files;

use LaraGram\Contracts\Support\Arrayable;
use InvalidArgumentException;
use JsonSerializable;
use LaraGram\Ai\Contracts\Files\StorableFile;
use LaraGram\Ai\Files\Concerns\CanBeUploadedToProvider;
use LaraGram\Ai\Files\Concerns\HasLocalContent;

class LocalImage extends Image implements Arrayable, JsonSerializable, StorableFile
{
    use CanBeUploadedToProvider;
    use HasLocalContent;

    public function __construct(public string $path, ?string $mimeType = null)
    {
        if (blank($path)) {
            throw new InvalidArgumentException('Image file path cannot be empty.');
        }

        $this->mime = $mimeType;
    }

    /**
     * Get the instance as an array.
     */
    public function toArray(): array
    {
        return [
            'type' => 'local-image',
            'name' => $this->name(),
            'path' => $this->path,
            'mime' => $this->mime,
        ];
    }
}
