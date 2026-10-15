<?php

namespace LaraGram\Ai\Responses;

use Countable;
use LaraGram\Ai\Responses\Concerns\SendsToTelegram;
use LaraGram\Ai\Telegram\Chat;
use LaraGram\Ai\Telegram\Sender;
use LaraGram\Ai\Telegram\Upload;
use LaraGram\Contracts\Support\Htmlable;
use LaraGram\Contracts\Support\Responsable;
use LaraGram\Support\Collection;
use LaraGram\Ai\Responses\Data\GeneratedImage;
use LaraGram\Ai\Responses\Data\ImageUsage;
use LaraGram\Ai\Responses\Data\Meta;
use RuntimeException;

class ImageResponse implements Responsable, \Stringable, Countable, Htmlable
{
    use SendsToTelegram;

    /**
     * @param  Collection<int, GeneratedImage>  $images
     */
    public function __construct(
        public Collection $images,
        public ImageUsage $usage,
        public Meta $meta,
    ) {}

    /**
     * Get the first image in the response.
     */
    public function firstImage(): GeneratedImage
    {
        if ($this->images->isEmpty()) {
            throw new RuntimeException('The image response does not contain any images.');
        }

        return $this->images->first();
    }

    /**
     * Store the image on a filesystem disk.
     */
    public function store(string $path = '', ?string $disk = null, array $options = []): string|bool
    {
        return $this->firstImage()->store($path, $disk, $options);
    }

    /**
     * Store the image on a filesystem disk with public visibility.
     */
    public function storePublicly(string $path = '', ?string $disk = null, array $options = []): string|bool
    {
        return $this->firstImage()->storePublicly($path, $disk, $options);
    }

    /**
     * Store the image on a filesystem disk with public visibility.
     */
    public function storePubliclyAs(string $path, ?string $name = null, ?string $disk = null, array $options = []): string|bool
    {
        return $this->firstImage()->storePubliclyAs($path, $name, $disk, $options);
    }

    /**
     * Store the image on a filesystem disk.
     */
    public function storeAs(string $path, ?string $name = null, ?string $disk = null, array $options = []): string|bool
    {
        return $this->firstImage()->storeAs($path, $name, $disk, $options);
    }

    /**
     * Get an <img> tag for the image.
     */
    public function toHtml(string $alt = ''): string
    {
        $image = $this->firstImage();

        return sprintf(
            '<img src="data:%s;base64,%s" alt="%s" />',
            $image->mime(),
            $image->image,
            e($alt),
        );
    }

    /**
     * Get the number of images that were generated.
     */
    public function count(): int
    {
        return count($this->images);
    }

    /**
     * Get the raw string content of the image.
     */
    public function __toString(): string
    {
        return (string) $this->firstImage();
    }

    /**
     * Send the images to the given chat, as a photo or an album.
     */
    protected function deliverTo(Chat $chat): void
    {
        $sender = Sender::to($chat);

        $sender->answerCallback();

        $uploads = $this->images->values()->map(fn (GeneratedImage $image, int $index) => new Upload(
            $image->content(),
            'image-'.($index + 1).'.'.(explode('/', $image->mime())[1] ?? 'png'),
            $image->mime(),
        ));

        if ($uploads->count() === 1) {
            $sender->call('sendPhoto', [...$chat->parameters(), 'photo' => $uploads->first()]);

            return;
        }

        foreach ($uploads->chunk(10) as $index => $album) {
            if ($album->count() === 1) {
                $sender->call('sendPhoto', [...$chat->parameters($index === 0), 'photo' => $album->first()]);

                continue;
            }

            $sender->call('sendMediaGroup', [
                ...$chat->parameters($index === 0),
                'media' => $album->values()->map(fn (Upload $upload) => ['type' => 'photo', 'media' => $upload])->all(),
            ]);
        }
    }
}
