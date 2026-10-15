<?php

namespace LaraGram\Ai\Gateway\Mistral\Concerns;

use LaraGram\Http\UploadedFile;
use LaraGram\Support\Collection;
use LaraGram\Support\Facades\Storage;
use InvalidArgumentException;
use LaraGram\Ai\Contracts\Files\StorableFile;
use LaraGram\Ai\Files\Base64Image;
use LaraGram\Ai\Files\Document;
use LaraGram\Ai\Files\File;
use LaraGram\Ai\Files\LocalImage;
use LaraGram\Ai\Files\RemoteDocument;
use LaraGram\Ai\Files\RemoteImage;
use LaraGram\Ai\Files\StoredImage;
use LaraGram\Ai\Gateway\Concerns\ResolvesDocumentFilenames;

trait MapsAttachments
{
    use ResolvesDocumentFilenames;

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
                $attachment instanceof RemoteDocument => [
                    'type' => 'document_url',
                    'document_url' => $attachment->url,
                    'document_name' => $attachment->name(),
                ],
                $attachment instanceof Document && $attachment instanceof StorableFile => [
                    'type' => 'document_url',
                    'document_url' => 'data:'.($attachment->mimeType() ?? 'application/pdf').';base64,'.base64_encode($attachment->content()),
                    'document_name' => $attachment->name() ?? $this->fallbackFilename($attachment->mimeType()),
                ],
                $attachment instanceof UploadedFile => [
                    'type' => 'document_url',
                    'document_url' => 'data:'.$attachment->getClientMimeType().';base64,'.base64_encode($attachment->get()),
                    'document_name' => $attachment->getClientOriginalName(),
                ],
                default => throw new InvalidArgumentException(
                    'Mistral only supports image and document attachments. Unsupported attachment type ['.$attachment::class.'].'
                ),
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
            'image/avif',
        ], true);
    }
}
