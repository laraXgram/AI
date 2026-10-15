<?php

namespace LaraGram\Ai\Contracts\Gateway;

use DateInterval;
use LaraGram\Support\Collection;
use LaraGram\Ai\Contracts\Providers\StoreProvider;
use LaraGram\Ai\Store;

interface StoreGateway
{
    /**
     * Get a vector store by its ID.
     */
    public function getStore(
        StoreProvider $provider,
        string $storeId,
    ): Store;

    /**
     * Create a new vector store.
     */
    public function createStore(
        StoreProvider $provider,
        string $name,
        ?string $description = null,
        ?Collection $fileIds = null,
        ?DateInterval $expiresWhenIdleFor = null,
    ): Store;

    /**
     * Add a file to a vector store.
     */
    public function addFile(
        StoreProvider $provider,
        string $storeId,
        string $fileId,
        array $metadata = [],
    ): string;

    /**
     * Remove a file from a vector store.
     */
    public function removeFile(
        StoreProvider $provider,
        string $storeId,
        string $documentId,
    ): bool;

    /**
     * Delete a vector store by its ID.
     */
    public function deleteStore(
        StoreProvider $provider,
        string $storeId,
    ): bool;
}
