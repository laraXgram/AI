<?php

namespace LaraGram\Ai\Responses;

use LaraGram\Ai\Contracts\Files\HasProviderId;

class AddedDocumentResponse implements HasProviderId
{
    public function __construct(
        public readonly string $id,
        public readonly ?string $fileId = null,
    ) {}

    /**
     * Get the provider document ID for the file that was added to the vector store.
     */
    public function id(): string
    {
        return $this->id;
    }

    /**
     * Get the provider ID for the file that was stored for later reference, if applicable.
     */
    public function fileId(): ?string
    {
        return $this->fileId;
    }
}
