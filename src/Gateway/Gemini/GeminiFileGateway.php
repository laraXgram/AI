<?php

namespace LaraGram\Ai\Gateway\Gemini;

use LaraGram\Http\Client\PendingRequest;
use LaraGram\Ai\Contracts\Files\StorableFile;
use LaraGram\Ai\Contracts\Gateway\FileGateway;
use LaraGram\Ai\Contracts\Providers\FileProvider;
use LaraGram\Ai\Enums\Lab;
use LaraGram\Ai\Gateway\Concerns\CreatesClient;
use LaraGram\Ai\Gateway\Concerns\HandlesFailoverErrors;
use LaraGram\Ai\Gateway\Concerns\PreparesStorableFiles;
use LaraGram\Ai\Providers\Provider;
use LaraGram\Ai\Responses\FileResponse;
use LaraGram\Ai\Responses\StoredFileResponse;

class GeminiFileGateway implements FileGateway
{
    use CreatesClient;
    use HandlesFailoverErrors;
    use PreparesStorableFiles;

    /**
     * Get a file by its ID.
     */
    public function getFile(FileProvider $provider, string $fileId): FileResponse
    {
        $fileId = str_starts_with($fileId, 'files/') ? $fileId : "files/{$fileId}";

        $response = $this->withErrorHandling(
            $provider->name(),
            fn () => $this->client($provider)->get($this->baseUrl($provider)."/{$fileId}")->throw(),
        );

        return new FileResponse(
            id: $response->json('name'),
            mimeType: $response->json('mimeType'),
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

        $uploadUrl = str_replace('/v1beta', '/upload/v1beta', $this->baseUrl($provider));

        [$providerOptions, $headers] = $this->resolveProviderOptionsAndHeaders($file, Lab::Gemini);

        $provider = $provider->withHeaders($headers);

        $response = $this->withErrorHandling($provider->name(), fn () => $this->client($provider)->attach(
            'file', $content, $name, ['Content-Type' => $mime]
        )->post("{$uploadUrl}/files", array_replace_recursive([
            'file' => ['display_name' => $name],
        ], $providerOptions))->throw());

        return new StoredFileResponse($response->json('file.name'));
    }

    /**
     * Delete a file by its ID.
     */
    public function deleteFile(FileProvider $provider, string $fileId): void
    {
        $fileId = str_starts_with($fileId, 'files/') ? $fileId : "files/{$fileId}";

        $this->withErrorHandling(
            $provider->name(),
            fn () => $this->client($provider)->delete($this->baseUrl($provider)."/{$fileId}")->throw(),
        );
    }

    protected function client(Provider $provider): PendingRequest
    {
        return $this->createClient(
            $this->baseUrl($provider),
            array_filter(['x-goog-api-key' => $provider->providerCredentials()['key']]),
            $provider->additionalConfiguration()['headers'] ?? [],
            timeout: null,
            throw: false,
        );
    }

    /**
     * Get the base URL for the Gemini API.
     */
    protected function baseUrl(Provider $provider): string
    {
        return rtrim($provider->additionalConfiguration()['url'] ?? 'https://generativelanguage.googleapis.com/v1beta', '/');
    }
}
