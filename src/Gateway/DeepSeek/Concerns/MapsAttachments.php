<?php

namespace LaraGram\Ai\Gateway\DeepSeek\Concerns;

use LaraGram\Http\UploadedFile;
use LaraGram\Support\Collection;
use LaraGram\Support\Facades\Storage;
use InvalidArgumentException;
use LaraGram\Ai\Files\Base64Image;
use LaraGram\Ai\Files\File;
use LaraGram\Ai\Files\LocalImage;
use LaraGram\Ai\Files\RemoteImage;
use LaraGram\Ai\Files\StoredImage;

trait MapsAttachments
{
    /**
     * Map the given LaraGram attachments to Chat Completions content parts.
     */
    protected function mapAttachments(Collection $attachments): array
    {
        return $attachments->map(function ($attachment): array {
            if (! $attachment instanceof File && ! $attachment instanceof UploadedFile) {
                throw new InvalidArgumentException(
                    'Unsupported attachment type ['.$attachment::class.']'
                );
            }

            return match (true) {
                $attachment instanceof Base64Image => [
                    'type' => 'image_url',
                    'image_url' => ['url' => 'data:'.$attachment->mime.';base64,'.$attachment->base64],
                ],
                $attachment instanceof RemoteImage => [
                    'type' => 'image_url',
                    'image_url' => ['url' => $attachment->url],
                ],
                $attachment instanceof LocalImage => [
                    'type' => 'image_url',
                    'image_url' => ['url' => 'data:'.($attachment->mimeType() ?? 'image/png').';base64,'.base64_encode(file_get_contents($attachment->path))],
                ],
                $attachment instanceof StoredImage => [
                    'type' => 'image_url',
                    'image_url' => ['url' => 'data:'.($attachment->mimeType() ?? 'image/png').';base64,'.base64_encode(
                        (string) Storage::disk($attachment->disk)->get($attachment->path)
                    )],
                ],
                $attachment instanceof UploadedFile && $this->isImage($attachment) => [
                    'type' => 'image_url',
                    'image_url' => ['url' => 'data:'.$attachment->getClientMimeType().';base64,'.base64_encode($attachment->get())],
                ],
                default => throw new InvalidArgumentException('DeepSeek does not support document attachments. Only image attachments are supported.'),
            };
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
