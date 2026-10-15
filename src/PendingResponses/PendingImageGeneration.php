<?php

namespace LaraGram\Ai\PendingResponses;

use LaraGram\Support\Traits\Conditionable;
use InvalidArgumentException;
use LaraGram\Ai\Ai;
use LaraGram\Ai\Enums\Lab;
use LaraGram\Ai\Events\ProviderFailedOver;
use LaraGram\Ai\Exceptions\FailoverableException;
use LaraGram\Ai\Files\Image;
use LaraGram\Ai\Files\LocalImage;
use LaraGram\Ai\Files\StoredImage;
use LaraGram\Ai\Jobs\GenerateImage;
use LaraGram\Ai\PendingResponses\Concerns\ResolvesProviderOptions;
use LaraGram\Ai\Prompts\QueuedImagePrompt;
use LaraGram\Ai\Providers\Provider;
use LaraGram\Ai\Responses\ImageResponse;
use LaraGram\Ai\Responses\QueuedImageResponse;
use LogicException;

class PendingImageGeneration
{
    use Conditionable;
    use ResolvesProviderOptions;

    public array $attachments = [];

    public ?string $size = null;

    public ?string $quality = null;

    public ?int $timeout = null;

    public function __construct(public string $prompt)
    {
        if (blank($prompt)) {
            throw new InvalidArgumentException('A prompt is required to generate an image.');
        }
    }

    /**
     * Provide the reference images that should be sent with the request.
     *
     * @param  array<Image>  $attachments
     */
    public function attachments(array $attachments): self
    {
        $this->attachments = $attachments;

        return $this;
    }

    /**
     * Specify the size / aspect ratio of the generated image.
     */
    public function size(string $size): self
    {
        $this->size = $size;

        return $this;
    }

    /**
     * Indicate that the generated image should have a square aspect ratio.
     */
    public function square(): self
    {
        $this->size = '1:1';

        return $this;
    }

    /**
     * Indicate that the generated image should have a portrait aspect ratio.
     */
    public function portrait(): self
    {
        $this->size = '2:3';

        return $this;
    }

    /**
     * Indicate that the generated image should have a landscape aspect ratio.
     */
    public function landscape(): self
    {
        $this->size = '3:2';

        return $this;
    }

    /**
     * Specify the quality of the generated image.
     *
     * @param  'low'|'medium'|'high'  $quality
     */
    public function quality(string $quality): self
    {
        $this->quality = $quality;

        return $this;
    }

    /**
     * Specify the timeout for the image generation request.
     */
    public function timeout(?int $timeout): self
    {
        $this->timeout = $timeout;

        return $this;
    }

    /**
     * Generate the image.
     *
     * @throws FailoverableException if every configured provider fails to generate the image.
     */
    public function generate(Lab|array|string|null $provider = null, ?string $model = null): ImageResponse
    {
        $providers = Provider::formatProviderAndModelList(
            $provider ?? config('ai.default_for_images'), $model
        );

        $lastException = null;

        foreach ($providers as $provider => $model) {
            $provider = Ai::fakeableImageProvider($provider);

            [$providerOptions, $headers] = $this->resolveProviderOptionsAndHeaders($provider);

            $model ??= $provider->defaultImageModel();

            try {
                return $provider->withHeaders($headers)->image(
                    $this->prompt, $this->attachments, $this->size, $this->quality, $model, $this->timeout, $providerOptions
                );
            } catch (FailoverableException $e) {
                $lastException = $e;

                event(new ProviderFailedOver($provider, $model, $e));

                continue;
            }
        }

        throw $lastException;
    }

    /**
     * Queue the generation of an image.
     *
     * @throws LogicException if any attachment is not a local image or an image stored on a filesystem disk.
     */
    public function queue(Lab|array|string|null $provider = null, ?string $model = null): QueuedImageResponse
    {
        $this->ensureAttachmentsAreQueueable();

        if (Ai::imagesAreFaked()) {
            Ai::recordImageGeneration(
                new QueuedImagePrompt(
                    $this->prompt,
                    $this->attachments,
                    $this->size,
                    $this->quality,
                    $provider,
                    $model,
                    $this->timeout,
                    $this->queuedProviderOptions(),
                )
            );
        }

        return new QueuedImageResponse(
            GenerateImage::dispatch($this, $provider, $model),
        );
    }

    /**
     * Ensure all of the attachments are queueable.
     */
    protected function ensureAttachmentsAreQueueable(): void
    {
        foreach ($this->attachments as $attachment) {
            if (! $attachment instanceof StoredImage &&
                ! $attachment instanceof LocalImage) {
                throw new LogicException('Only local images or images stored on a filesystem disk may be attachments for queued image generations.');
            }
        }
    }
}
