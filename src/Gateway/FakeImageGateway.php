<?php

namespace LaraGram\Ai\Gateway;

use Closure;
use LaraGram\Support\Collection;
use LaraGram\Ai\Contracts\Gateway\ImageGateway;
use LaraGram\Ai\Contracts\Providers\ImageProvider;
use LaraGram\Ai\Files\Image;
use LaraGram\Ai\Prompts\ImagePrompt;
use LaraGram\Ai\Responses\Data\GeneratedImage;
use LaraGram\Ai\Responses\Data\ImageUsage;
use LaraGram\Ai\Responses\Data\Meta;
use LaraGram\Ai\Responses\ImageResponse;
use RuntimeException;

class FakeImageGateway implements ImageGateway
{
    protected int $currentResponseIndex = 0;

    protected bool $preventStrayGenerations = false;

    public function __construct(
        protected Closure|array $responses = [],
    ) {}

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
    ): ImageResponse {
        $imagePrompt = new ImagePrompt($prompt, $attachments, $size, $quality, $provider, $model, $timeout, $providerOptions);

        return $this->nextResponse($provider, $model, $imagePrompt);
    }

    /**
     * Get the next response instance.
     */
    protected function nextResponse(ImageProvider $provider, string $model, ImagePrompt $prompt): ImageResponse
    {
        $response = is_array($this->responses)
            ? ($this->responses[$this->currentResponseIndex] ?? null)
            : call_user_func($this->responses, $prompt);

        return tap($this->marshalResponse(
            $response, $provider, $model, $prompt
        ), fn (): int => $this->currentResponseIndex++);
    }

    /**
     * Marshal the given response into a full response instance.
     */
    protected function marshalResponse(
        mixed $response,
        ImageProvider $provider,
        string $model,
        ImagePrompt $prompt
    ): ImageResponse {
        if ($response instanceof Closure) {
            $response = $response($prompt);
        }

        if (is_null($response)) {
            if ($this->preventStrayGenerations) {
                throw new RuntimeException('Attempted image generation without a fake response.');
            }

            $response = base64_encode('fake-image-content');
        }

        if (is_string($response)) {
            return new ImageResponse(
                new Collection([new GeneratedImage($response, 'image/png')]),
                new ImageUsage,
                new Meta($provider->name(), $model),
            );
        }

        return $response;
    }

    /**
     * Indicate that an exception should be thrown if any image generation is not faked.
     */
    public function preventStrayImages(bool $prevent = true): self
    {
        $this->preventStrayGenerations = $prevent;

        return $this;
    }
}
