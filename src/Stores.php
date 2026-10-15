<?php

namespace LaraGram\Ai;

use Closure;
use DateInterval;
use LaraGram\Support\Collection;
use LaraGram\Ai\Gateway\FakeStoreGateway;

class Stores
{
    /**
     * Get a vector store by its ID.
     */
    public static function get(string $storeId, ?string $provider = null): Store
    {
        return Ai::fakeableStoreProvider($provider)->getStore($storeId);
    }

    /**
     * Create a new vector store.
     */
    public static function create(
        string $name,
        ?string $description = null,
        Collection|array $fileIds = [],
        ?DateInterval $expiresWhenIdleFor = null,
        ?string $provider = null): Store
    {
        return Ai::fakeableStoreProvider($provider)->createStore(
            $name, $description, Collection::make($fileIds), $expiresWhenIdleFor
        );
    }

    /**
     * Delete a vector store.
     */
    public static function delete(string $storeId, ?string $provider = null): bool
    {
        return Ai::fakeableStoreProvider($provider)->deleteStore($storeId);
    }

    /**
     * Fake store operations.
     */
    public static function fake(Closure|array $responses = [], bool $files = true): FakeStoreGateway
    {
        if ($files) {
            Files::fake();
        }

        return Ai::fakeStores($responses);
    }

    /**
     * Get the fake store ID for a given store name.
     */
    public static function fakeId(string $for): string
    {
        return 'fake_store_'.md5($for);
    }

    /**
     * Determine if store operations are faked.
     */
    public static function isFaked(): bool
    {
        return Ai::storesAreFaked();
    }
}
