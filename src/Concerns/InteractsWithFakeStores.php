<?php

namespace LaraGram\Ai\Concerns;

use Closure;
use DateInterval;
use LaraGram\Http\UploadedFile;
use LaraGram\Support\Collection;
use LaraGram\Ai\Contracts\Files\HasProviderId;
use LaraGram\Ai\Contracts\Files\StorableFile;
use LaraGram\Ai\Files;
use LaraGram\Ai\Gateway\FakeStoreGateway;
use LaraGram\Ai\Stores;

trait InteractsWithFakeStores
{
    /**
     * The fake store gateway instance.
     */
    protected ?FakeStoreGateway $fakeStoreGateway = null;

    /**
     * All of the recorded store creations.
     */
    protected array $recordedStoreCreations = [];

    /**
     * All of the recorded store deletions.
     */
    protected array $recordedStoreDeletions = [];

    /**
     * All of the recorded file additions.
     */
    protected array $recordedFileAdditions = [];

    /**
     * All of the recorded file removals.
     */
    protected array $recordedFileRemovals = [];

    /**
     * Fake store operations.
     */
    public function fakeStores(Closure|array $responses = []): FakeStoreGateway
    {
        return $this->fakeStoreGateway = new FakeStoreGateway($responses);
    }

    /**
     * Record a store creation.
     */
    public function recordStoreCreation(
        string $name,
        ?string $description = null,
        ?Collection $fileIds = null,
        ?DateInterval $expiresWhenIdleFor = null,
    ): self {
        $this->recordedStoreCreations[] = [
            'name' => $name,
            'description' => $description,
            'fileIds' => $fileIds,
            'expiresWhenIdleFor' => $expiresWhenIdleFor,
        ];

        return $this;
    }

    /**
     * Record a store deletion.
     */
    public function recordStoreDeletion(string $storeId): self
    {
        $this->recordedStoreDeletions[] = $storeId;

        return $this;
    }

    /**
     * Record a file addition to a store.
     */
    public function recordFileAddition(
        string $storeId,
        string $fileId,
        StorableFile|UploadedFile|HasProviderId|string $file,
    ): self {
        $this->recordedFileAdditions[] = [
            'storeId' => $storeId,
            'fileId' => $fileId,
            'file' => $file,
        ];

        return $this;
    }

    /**
     * Record a file removal from a store.
     */
    public function recordFileRemoval(string $storeId, string $fileId): self
    {
        $this->recordedFileRemovals[] = [
            'storeId' => $storeId,
            'fileId' => $fileId,
        ];

        return $this;
    }

    /**
     * Get a callback for matching store and file IDs.
     */
    protected function fileMatchingCallback(Closure|string $storeId, ?string $fileId): Closure
    {
        if ($storeId instanceof Closure) {
            return $storeId;
        }

        $expectedStoreId = str_starts_with($storeId, 'fake_store_') ? $storeId : Stores::fakeId($storeId);
        $expectedFileId = $fileId !== null ? (str_starts_with($fileId, 'fake_file_') ? $fileId : Files::fakeId($fileId))
            : null;

        return fn ($s, $f): bool => $s === $expectedStoreId && $this->fileIdMatches($f, $expectedFileId);
    }

    /**
     * Determine if the given file matches the expected file ID.
     */
    protected function fileIdMatches(StorableFile|UploadedFile|HasProviderId|string $file, ?string $expectedFileId): bool
    {
        if ($expectedFileId === null) {
            return true;
        }

        return match (true) {
            $file instanceof HasProviderId => $file->id() === $expectedFileId,
            is_string($file) => $file === $expectedFileId,
            default => false,
        };
    }

    /**
     * Determine if store operations are faked.
     */
    public function storesAreFaked(): bool
    {
        return $this->fakeStoreGateway !== null;
    }

    /**
     * Get the fake store gateway.
     */
    public function fakeStoreGateway(): ?FakeStoreGateway
    {
        return $this->fakeStoreGateway;
    }
}
