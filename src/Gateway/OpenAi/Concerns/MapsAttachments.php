<?php

namespace LaraGram\Ai\Gateway\OpenAi\Concerns;

use LaraGram\Http\UploadedFile;
use LaraGram\Support\Collection;
use LaraGram\Support\Facades\Storage;
use InvalidArgumentException;
use LaraGram\Ai\Contracts\HasProviderOptions;
use LaraGram\Ai\Enums\Lab;
use LaraGram\Ai\Files\Base64Document;
use LaraGram\Ai\Files\Base64Image;
use LaraGram\Ai\Files\File;
use LaraGram\Ai\Files\LocalDocument;
use LaraGram\Ai\Files\LocalImage;
use LaraGram\Ai\Files\ProviderDocument;
use LaraGram\Ai\Files\ProviderImage;
use LaraGram\Ai\Files\RemoteDocument;
use LaraGram\Ai\Files\RemoteImage;
use LaraGram\Ai\Files\StoredDocument;
use LaraGram\Ai\Files\StoredImage;
use LaraGram\Ai\Gateway\Concerns\ResolvesDocumentFilenames;
use LaraGram\Ai\Providers\Provider;

trait MapsAttachments
{
    use ResolvesDocumentFilenames;

    /**
     * Map the given LaraGram attachments to OpenAI content parts.
     */
    protected function mapAttachments(Collection $attachments, Provider $provider): array
    {
        $providerKey = Lab::tryFrom($provider->driver()) ?? $provider->driver();

        return $attachments->map(function ($attachment) use ($providerKey): array {
            if (! $attachment instanceof File && ! $attachment instanceof UploadedFile) {
                throw new InvalidArgumentException(
                    'Unsupported attachment type ['.$attachment::class.']'
                );
            }

            $part = match (true) {
                $attachment instanceof ProviderImage => [
                    'type' => 'input_image',
                    'file_id' => $attachment->id,
                ],
                $attachment instanceof Base64Image => [
                    'type' => 'input_image',
                    'image_url' => 'data:'.$attachment->mime.';base64,'.$attachment->base64,
                ],
                $attachment instanceof RemoteImage => [
                    'type' => 'input_image',
                    'image_url' => $attachment->url,
                ],
                $attachment instanceof LocalImage => [
                    'type' => 'input_image',
                    'image_url' => 'data:'.($attachment->mimeType() ?? 'image/png').';base64,'.base64_encode(file_get_contents($attachment->path)),
                ],
                $attachment instanceof StoredImage => [
                    'type' => 'input_image',
                    'image_url' => 'data:'.($attachment->mimeType() ?? 'image/png').';base64,'.base64_encode(
                        (string) Storage::disk($attachment->disk)->get($attachment->path)
                    ),
                ],
                $attachment instanceof ProviderDocument => array_filter([
                    'type' => 'input_file',
                    'file_id' => $attachment->id,
                ]),
                $attachment instanceof Base64Document => [
                    'type' => 'input_file',
                    'file_data' => 'data:'.$attachment->mime.';base64,'.$attachment->base64,
                    'filename' => $attachment->name() ?? $this->fallbackFilename($attachment->mime),
                ],
                $attachment instanceof LocalDocument => [
                    'type' => 'input_file',
                    'file_data' => 'data:'.($attachment->mimeType() ?? 'application/octet-stream').';base64,'.base64_encode(file_get_contents($attachment->path)),
                    'filename' => $attachment->name() ?? $this->fallbackFilename($attachment->mimeType()),
                ],
                $attachment instanceof RemoteDocument => array_filter([
                    'type' => 'input_file',
                    'file_url' => $attachment->url,
                    'filename' => $attachment->name(),
                ]),
                $attachment instanceof StoredDocument => [
                    'type' => 'input_file',
                    'file_data' => 'data:'.($attachment->mimeType() ?? 'application/octet-stream').';base64,'.base64_encode(
                        (string) Storage::disk($attachment->disk)->get($attachment->path)
                    ),
                    'filename' => $attachment->name() ?? $this->fallbackFilename($attachment->mimeType()),
                ],
                $attachment instanceof UploadedFile && $this->isImage($attachment) => [
                    'type' => 'input_image',
                    'image_url' => 'data:'.$attachment->getClientMimeType().';base64,'.base64_encode($attachment->get()),
                ],
                $attachment instanceof UploadedFile => [
                    'type' => 'input_file',
                    'file_data' => 'data:'.$attachment->getClientMimeType().';base64,'.base64_encode($attachment->get()),
                    'filename' => $attachment->getClientOriginalName(),
                ],
                default => throw new InvalidArgumentException('Unsupported attachment type ['.$attachment::class.']'),
            };

            return $attachment instanceof HasProviderOptions
                ? array_merge($attachment->providerOptions($providerKey), $part)
                : $part;
        })->all();
    }

    /**
     * Determine if the given uploaded file is an image.
     */
    protected function isImage(UploadedFile $attachment): bool
    {
        return in_array($attachment->getClientMimeType(), [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
        ],
            true);
    }
}
