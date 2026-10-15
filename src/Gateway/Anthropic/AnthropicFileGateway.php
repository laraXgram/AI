<?php

namespace LaraGram\Ai\Gateway\Anthropic;

use LaraGram\Http\Client\PendingRequest;
use LaraGram\Ai\Contracts\Files\StorableFile;
use LaraGram\Ai\Contracts\Gateway\FileGateway;
use LaraGram\Ai\Contracts\Providers\FileProvider;
use LaraGram\Ai\Enums\Lab;
use LaraGram\Ai\Gateway\Concerns\HandlesFailoverErrors;
use LaraGram\Ai\Gateway\Concerns\PreparesStorableFiles;
use LaraGram\Ai\Providers\Provider;
use LaraGram\Ai\Responses\FileResponse;
use LaraGram\Ai\Responses\StoredFileResponse;

class AnthropicFileGateway implements FileGateway
{
    use Concerns\CreatesAnthropicClient;
    use HandlesFailoverErrors;
    use PreparesStorableFiles;

    /**
     * Get a file by its ID.
     */
    public function getFile(FileProvider $provider, string $fileId): FileResponse
    {
        $response = $this->withErrorHandling(
            $provider->name(),
            fn () => $this->client($provider)->get("files/{$fileId}"),
        );

        return new FileResponse(
            id: $response->json('id'),
            mimeType: $response->json('mime_type'),
        );
    }

    /**
     * Store the given file.
     */
    public function putFile(
        FileProvider $provider,
        StorableFile $file,
    ): StoredFileResponse {
        [$content, $mime, $name] = $this->prepareStorableFile($file);

        [$providerOptions, $headers] = $this->resolveProviderOptionsAndHeaders($file, Lab::Anthropic);

        $provider = $provider->withHeaders($headers);

        $response = $this->withErrorHandling(
            $provider->name(),
            fn () => $this->client($provider)
                ->attach('file', $content, $name, ['Content-Type' => $mime])
                ->post('files', $providerOptions),
        );

        return new StoredFileResponse($response->json('id'));
    }

    /**
     * Delete a file by its ID.
     */
    public function deleteFile(FileProvider $provider, string $fileId): void
    {
        $this->withErrorHandling(
            $provider->name(),
            fn () => $this->client($provider)->delete("files/{$fileId}"),
        );
    }

    /**
     * Get an HTTP client for the Anthropic Files API.
     */
    protected function client(Provider $provider, ?int $timeout = null): PendingRequest
    {
        $config = $provider->additionalConfiguration();

        return $this->createClient(
            $this->baseUrl($provider),
            array_filter([
                'x-api-key' => $provider->providerCredentials()['key'],
                'anthropic-version' => $config['version'] ?? '2023-06-01',
                'anthropic-beta' => 'files-api-2025-04-14',
            ]),
            $config['headers'] ?? [],
            $timeout ?? 60,
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function overloadedStatusCodes(): array
    {
        // 529 is Anthropic's own "overloaded" status, plus the shared transient gateway and Cloudflare codes.
        return [529, 502, 503, 504, 520, 522, 524];
    }
}
