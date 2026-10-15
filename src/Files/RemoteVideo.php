<?php

namespace LaraGram\Ai\Files;

use LaraGram\Contracts\Support\Arrayable;
use InvalidArgumentException;
use JsonSerializable;
use LaraGram\Ai\Contracts\Files\StorableFile;
use LaraGram\Ai\Files\Concerns\CanBeUploadedToProvider;
use LaraGram\Ai\Files\Concerns\HasRemoteContent;

class RemoteVideo extends Video implements Arrayable, JsonSerializable, StorableFile
{
    use CanBeUploadedToProvider, HasRemoteContent;

    public function __construct(public string $url, ?string $mimeType = null)
    {
        if (blank($url)) {
            throw new InvalidArgumentException('Remote video URL cannot be empty.');
        }

        $this->mime = $mimeType;
    }

    /**
     * Get the instance as an array.
     */
    public function toArray(): array
    {
        return [
            'type' => 'remote-video',
            'name' => $this->name(),
            'url' => $this->url,
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
}
