<?php

namespace LaraGram\Ai\Contracts\Providers;

use LaraGram\Ai\Contracts\Files\StorableFile;
use LaraGram\Ai\Contracts\Gateway\FileGateway;
use LaraGram\Ai\Responses\FileResponse;
use LaraGram\Ai\Responses\StoredFileResponse;

interface FileProvider extends Provider
{
    /**
     * Get a file by its ID.
     */
    public function getFile(string $fileId): FileResponse;

    /**
     * Store the given file.
     */
    public function putFile(StorableFile $file): StoredFileResponse;

    /**
     * Delete a file by its ID.
     */
    public function deleteFile(string $fileId): void;

    /**
     * Get the provider's file gateway.
     */
    public function fileGateway(): FileGateway;

    /**
     * Set the provider's file gateway.
     */
    public function useFileGateway(FileGateway $gateway): self;
}
