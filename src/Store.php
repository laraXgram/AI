<?php

namespace LaraGram\Ai;

use LaraGram\Ai\Files\TelegramFile;
use Closure;
use LaraGram\Http\UploadedFile;
use LaraGram\Ai\Contracts\Files\HasProviderId;
use LaraGram\Ai\Contracts\Files\StorableFile;
use LaraGram\Ai\Contracts\Providers\FileProvider;
use LaraGram\Ai\Contracts\Providers\StoreProvider;
use LaraGram\Ai\Files\LocalDocument;
use LaraGram\Ai\Files\ProviderDocument;
use LaraGram\Ai\Responses\AddedDocumentResponse;
use LaraGram\Ai\Responses\Data\StoreFileCounts;

class Store
{
    public function __construct(
        protected FileProvider&StoreProvider $provider,
        public readonly string $id,
        public readonly ?string $name,
        public readonly StoreFileCounts $fileCounts,
        public readonly bool $ready,
    ) {}

    /**
     * Add a file to the store.
     */
    public function add(
        mixed $file,
        array $metadata = [],
    ): AddedDocumentResponse {
        if (TelegramFile::is($file)) {
            $file = TelegramFile::toFile($file);
        }

        if ($file instanceof UploadedFile) {
            $file = LocalDocument::fromUploadedFile($file);
        }

        $originalFile = $file;

        if ($file instanceof StorableFile) {
            $file = $this->storeFile($file);
        }

        if (Ai::storesAreFaked()) {
            Ai::recordFileAddition($this->id, $file instanceof HasProviderId ? $file->id() : $file, $originalFile);
        }

        return new AddedDocumentResponse($this->provider->addFileToStore($this->id, match (true) {
            is_string($file) => new ProviderDocument($file),
            default => $file,
        }, $metadata), match (true) {
            $file instanceof HasProviderId => $file->id(),
            is_string($file) => $file,
            default => null,
        });
    }

    /**
     * Resolve the ID of the file a document was imported from, which Gemini does not reuse as the document ID.
     */
    protected function fileIdFor(HasProviderId|string $documentId): string
    {
        if ($documentId instanceof AddedDocumentResponse && $documentId->fileId() !== null) {
            return $documentId->fileId();
        }

        return $documentId instanceof HasProviderId ? $documentId->id() : $documentId;
    }

    /**
     * Store the given file with the provider.
     */
    protected function storeFile(StorableFile $file): HasProviderId
    {
        return Files::put($file, provider: $this->provider->name());
    }

    /**
     * Remove a document from the store.
     */
    public function remove(HasProviderId|string $documentId, bool $deleteFile = false): bool
    {
        $removed = $this->provider->removeFileFromStore($this->id, $documentId);

        if ($deleteFile && $removed) {
            Files::delete($this->fileIdFor($documentId), provider: $this->provider->name());
        }

        return $removed;
    }

    /**
     * Refresh the store from the provider.
     */
    public function refresh(): self
    {
        return $this->provider->getStore($this->id);
    }

    /**
     * Delete the store from the provider.
     */
    public function delete(): bool
    {
        return $this->provider->deleteStore($this->id);
    }

    /**
     * Get a callback for matching file assertions on this store.
     */
    protected function fileAssertionCallback(Closure|string $fileId): Closure
    {
        if ($fileId instanceof Closure) {
            return fn ($s, $f): bool => $s === $this->id && $fileId($f);
        }

        $expectedFileId = str_starts_with($fileId, 'fake_file_') ? $fileId : Files::fakeId($fileId);

        return fn ($s, $f): bool => $s === $this->id && $this->fileIdMatches($f, $expectedFileId);
    }

    /**
     * Determine if the given file matches the expected file ID.
     */
    protected function fileIdMatches(
        StorableFile|HasProviderId|string $file,
        string $expectedFileId,
    ): bool {
        return match (true) {
            $file instanceof HasProviderId => $file->id() === $expectedFileId,
            is_string($file) => $file === $expectedFileId,
            default => false,
        };
    }
}
