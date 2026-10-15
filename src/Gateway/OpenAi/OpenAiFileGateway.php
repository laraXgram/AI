<?php

namespace LaraGram\Ai\Gateway\OpenAi;

use LaraGram\Ai\Contracts\Files\StorableFile;
use LaraGram\Ai\Contracts\Gateway\FileGateway;
use LaraGram\Ai\Contracts\Providers\FileProvider;
use LaraGram\Ai\Enums\Lab;
use LaraGram\Ai\Gateway\Concerns\HandlesFailoverErrors;
use LaraGram\Ai\Gateway\Concerns\PreparesStorableFiles;
use LaraGram\Ai\Responses\FileResponse;
use LaraGram\Ai\Responses\StoredFileResponse;

class OpenAiFileGateway implements FileGateway
{
    use Concerns\CreatesOpenAiClient;
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

        [$providerOptions, $headers] = $this->resolveProviderOptionsAndHeaders($file, $this->providerOptionsKey());

        $provider = $provider->withHeaders($headers);

        $response = $this->withErrorHandling(
            $provider->name(),
            fn () => $this->client($provider)
                ->attach('file', $content, $name, ['Content-Type' => $mime])
                ->post('files', array_merge(
                    ['purpose' => $this->defaultPurpose()],
                    $providerOptions,
                ))
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
            fn () => $this->client($provider)
                ->delete("files/{$fileId}")
        );
    }

    /**
     * Get the default purpose to use when a file does not specify one.
     */
    protected function defaultPurpose(): string
    {
        return 'user_data';
    }

    /**
     * Get the provider key used to resolve file upload options.
     */
    protected function providerOptionsKey(): Lab
    {
        return Lab::OpenAI;
    }
}
