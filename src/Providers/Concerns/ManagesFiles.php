<?php

namespace LaraGram\Ai\Providers\Concerns;

use LaraGram\Support\Str;
use LaraGram\Ai\Ai;
use LaraGram\Ai\Contracts\Files\StorableFile;
use LaraGram\Ai\Events\FileDeleted;
use LaraGram\Ai\Events\FileStored;
use LaraGram\Ai\Events\StoringFile;
use LaraGram\Ai\Events\StoringFileFailed;
use LaraGram\Ai\Responses\FileResponse;
use LaraGram\Ai\Responses\StoredFileResponse;
use Throwable;

trait ManagesFiles
{
    /**
     * Get a file by its ID.
     */
    public function getFile(string $fileId): FileResponse
    {
        return $this->fileGateway()->getFile($this, $fileId);
    }

    /**
     * Store the given file.
     */
    public function putFile(StorableFile $file): StoredFileResponse
    {
        $invocationId = (string) Str::uuid7();

        if (Ai::filesAreFaked()) {
            Ai::recordFileUpload($file);
        }

        $this->events->dispatch(new StoringFile(
            $invocationId, $this, $file
        ));

        return tap(
            $this->dispatchingFailure(
                fn () => $this->fileGateway()->putFile($this, $file),
                fn (Throwable $exception) => new StoringFileFailed($invocationId, $this, $file, $exception),
            ),
            function (StoredFileResponse $response) use ($invocationId, $file): void {
                $this->events->dispatch(new FileStored(
                    $invocationId, $this, $file, $response,
                ));
            }
        );
    }

    /**
     * Delete a file by its ID.
     */
    public function deleteFile(string $fileId): void
    {
        $invocationId = (string) Str::uuid7();

        if (Ai::filesAreFaked()) {
            Ai::recordFileDeletion($fileId);
        }

        $this->fileGateway()->deleteFile($this, $fileId);

        $this->events->dispatch(new FileDeleted(
            $invocationId, $this, $fileId,
        ));
    }
}
