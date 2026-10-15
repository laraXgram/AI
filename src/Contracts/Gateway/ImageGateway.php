<?php

namespace LaraGram\Ai\Contracts\Gateway;

use LaraGram\Ai\Contracts\Providers\ImageProvider;
use LaraGram\Ai\Files\Image;
use LaraGram\Ai\Responses\ImageResponse;

interface ImageGateway
{
    /**
     * Generate an image.
     *
     * @param  array<Image>  $attachments
     * @param  'low'|'medium'|'high'|null  $quality
     * @param  array<string, mixed>  $providerOptions
     */
    public function generateImage(
        ImageProvider $provider,
        string $model,
        string $prompt,
        array $attachments = [],
        ?string $size = null,
        ?string $quality = null,
        ?int $timeout = null,
        array $providerOptions = [],
    ): ImageResponse;
}
