<?php

namespace LaraGram\Ai\Gateway\OpenRouter;

use LaraGram\Ai\Contracts\Files\StorableFile;
use LaraGram\Ai\Contracts\Gateway\FileGateway;
use LaraGram\Ai\Contracts\Providers\FileProvider;
use LaraGram\Ai\Enums\Lab;
use LaraGram\Ai\Gateway\Concerns\HandlesFailoverErrors;
use LaraGram\Ai\Gateway\Concerns\PreparesStorableFiles;
use LaraGram\Ai\Providers\Provider;
use LaraGram\Ai\Responses\FileResponse;
use LaraGram\Ai\Responses\StoredFileResponse;

class OpenRouterFileGateway implements FileGateway
{
    use Concerns\CreatesOpenRouterClient;
    use HandlesFailoverErrors;
    use PreparesStorableFiles;

    /**
     * Get a file by its ID.
     */
    public function getFile(FileProvider $provider, string $fileId): FileResponse
    {
        $response = $this->withErrorHandling(
            $provider->name(),
            fn () => $this->client($provider)
                ->get("files/{$fileId}")
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

        [$providerOptions, $headers] = $this->resolveProviderOptionsAndHeaders($file, Lab::OpenRouter);

        $provider = $provider->withHeaders($headers);

        $response = $this->withErrorHandling(
            $provider->name(),
            fn () => $this->client($provider)
                ->attach('file', $content, $name, ['Content-Type' => $mime])
                ->post('files', $providerOptions)
        );

        return new StoredFileResponse($response->json('id'));
    }

    /**
     * Get the base URL for the Files API, which rejects the in-region endpoints with a 403.
     */
    protected function baseUrl(Provider $provider): string
    {
        return 'https://openrouter.ai/api/v1';
    }

    /**
     * Delete a file by its ID.
     */
    public function deleteFile(FileProvider $provider, string $fileId): void
    {
        $this->withErrorHandling(
            $provider->name(),
            fn () => $this->client($provider)
                ->delete("files/{$fileId}")
        );
    }
}
