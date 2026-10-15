<?php

namespace LaraGram\Ai\Gateway\Concerns;

use LaraGram\Http\Client\PendingRequest;
use LaraGram\Support\Collection;
use LaraGram\Support\Facades\Http;

trait CreatesClient
{
    /**
     * @param  array<string, mixed>  $headers
     * @param  array<string, mixed>  $configuredHeaders
     */
    protected function createClient(
        string $baseUrl,
        array $headers = [],
        array $configuredHeaders = [],
        ?int $timeout = 60,
        bool $throw = true,
    ): PendingRequest {
        $headers = collect($headers)
            ->merge($configuredHeaders)
            ->groupBy(fn (mixed $value, string $name): string => strtolower($name), preserveKeys: true)
            ->mapWithKeys(fn (Collection $group): array => [$group->keys()->first() => $group->last()])
            ->all();

        return Http::baseUrl($baseUrl)
            ->withHeaders($headers)
            ->when($timeout !== null, fn (PendingRequest $client) => $client->timeout($timeout))
            ->when($throw, fn (PendingRequest $client) => $client->throw());
    }
}
