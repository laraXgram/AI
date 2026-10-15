<?php

namespace LaraGram\Ai\Gateway\AzureOpenAi\Concerns;

use LaraGram\Http\Client\PendingRequest;
use LaraGram\Ai\Gateway\Concerns\CreatesClient;
use LaraGram\Ai\Providers\Provider;

trait CreatesAzureOpenAiClient
{
    use CreatesClient;

    /**
     * Get an HTTP client for the Azure OpenAI v1-compatible API.
     */
    protected function client(Provider $provider, ?int $timeout = null): PendingRequest
    {
        $config = $provider->additionalConfiguration();

        $base = rtrim($config['url'] ?? '', '/');

        return $this->createClient(
            "{$base}/openai/v1",
            ['api-key' => $provider->providerCredentials()['key']],
            $config['headers'] ?? [],
            $timeout ?? 60,
        );
    }
}
