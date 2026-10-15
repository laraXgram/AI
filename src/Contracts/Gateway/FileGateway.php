<?php

namespace LaraGram\Ai\Contracts\Gateway;

use LaraGram\Ai\Contracts\Files\StorableFile;
use LaraGram\Ai\Contracts\Providers\FileProvider;
use LaraGram\Ai\Responses\FileResponse;
use LaraGram\Ai\Responses\StoredFileResponse;

interface FileGateway
{
    /**
     * Get a file by its ID.
     */
    public function getFile(
        FileProvider $provider,
        string $fileId,
    ): FileResponse;

    /**
     * Store the given file.
     */
    public function putFile(
        FileProvider $provider,
        StorableFile $file,
    ): StoredFileResponse;

    /**
     * Delete a file by its ID.
     */
    public function deleteFile(
        FileProvider $provider,
        string $fileId,
    ): void;
}
