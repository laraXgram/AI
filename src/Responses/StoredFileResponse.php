<?php

namespace LaraGram\Ai\Responses;

use LaraGram\Ai\Contracts\Files\HasProviderId;

class StoredFileResponse implements HasProviderId
{
    public function __construct(
        public readonly string $id,
    ) {}

    /**
     * Get the provider ID for the stored file.
     */
    public function id(): string
    {
        return $this->id;
    }
}
