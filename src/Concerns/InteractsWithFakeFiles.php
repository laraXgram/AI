<?php

namespace LaraGram\Ai\Concerns;

use Closure;
use LaraGram\Support\Collection;
use LaraGram\Ai\Contracts\Files\StorableFile;
use LaraGram\Ai\Gateway\FakeFileGateway;

trait InteractsWithFakeFiles
{
    /**
     * The fake file gateway instance.
     */
    protected ?FakeFileGateway $fakeFileGateway = null;

    /**
     * All of the recorded file uploads.
     */
    protected array $recordedFileUploads = [];

    /**
     * All of the recorded file deletions.
     */
    protected array $recordedFileDeletions = [];

    /**
     * Fake file operations.
     */
    public function fakeFiles(Closure|array $responses = []): FakeFileGateway
    {
        return $this->fakeFileGateway = new FakeFileGateway($responses);
    }

    /**
     * Record a file upload.
     */
    public function recordFileUpload(StorableFile $file): self
    {
        $this->recordedFileUploads[] = [
            'file' => $file,
        ];

        return $this;
    }

    /**
     * Record a file deletion.
     */
    public function recordFileDeletion(string $fileId): self
    {
        $this->recordedFileDeletions[] = $fileId;

        return $this;
    }

    /**
     * Determine if file operations are faked.
     */
    public function filesAreFaked(): bool
    {
        return $this->fakeFileGateway !== null;
    }

    /**
     * Get the fake file gateway.
     */
    public function fakeFileGateway(): ?FakeFileGateway
    {
        return $this->fakeFileGateway;
    }
}
