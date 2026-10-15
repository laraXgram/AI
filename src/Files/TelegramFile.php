<?php

namespace LaraGram\Ai\Files;

use InvalidArgumentException;
use LaraGram\Request\Files\FileBag;
use LaraGram\Request\Files\MediaFile;
use LaraGram\Support\Facades\Http;
use RuntimeException;

class TelegramFile
{
    /**
     * Determine if the value is a file received from Telegram.
     *
     * That is a file of a bot update, such as `$request->file()`, or an MTProto
     * update carrying media.
     */
    public static function is(mixed $value): bool
    {
        return $value instanceof FileBag
            || $value instanceof MediaFile
            || $value instanceof \LaraGram\MTProto\Foundation\ClientRequest;
    }

    /**
     * Replace the Telegram files in the given list with attachments, expanding albums.
     *
     * @param  iterable<int, mixed>  $files
     * @return array<int, mixed>
     */
    public static function normalize(iterable $files): array
    {
        $normalized = [];

        foreach ($files as $file) {
            if (static::is($file)) {
                array_push($normalized, ...static::toFiles($file));
            } else {
                $normalized[] = $file;
            }
        }

        return $normalized;
    }

    /**
     * Convert a Telegram file, file bag or MTProto update into attachments.
     *
     * Photos are attached at their largest size, and every item of an album is attached.
     *
     * @return array<int, \LaraGram\Ai\Files\File>
     */
    public static function toFiles(mixed $value): array
    {
        if ($value instanceof FileBag) {
            if ($value->isEmpty()) {
                return [];
            }

            return $value->type() === 'photo' && ! $value->isAlbum()
                ? [static::toFile($value->last())]
                : array_map(fn (MediaFile $file) => static::toFile($file->largest()), $value->isAlbum() ? $value->all() : [$value->first()]);
        }

        return [static::toFile($value)];
    }

    /**
     * Convert a single Telegram file into an attachment of the matching kind.
     */
    public static function toFile(mixed $file): File
    {
        if ($file instanceof FileBag) {
            return static::toFiles($file)[0] ?? throw new InvalidArgumentException('The Telegram update has no file.');
        }

        [$contents, $type, $mime, $name] = match (true) {
            $file instanceof MediaFile => static::fromMediaFile($file),
            $file instanceof \LaraGram\MTProto\Foundation\ClientRequest => static::fromClientRequest($file),
            default => throw new InvalidArgumentException('The value is not a Telegram file.'),
        };

        $kind = match (true) {
            in_array($type, ['photo'], true), str_starts_with((string) $mime, 'image/') && $type !== 'sticker' => 'image',
            in_array($type, ['voice', 'audio'], true), str_starts_with((string) $mime, 'audio/') => 'audio',
            in_array($type, ['video', 'video_note', 'animation'], true), str_starts_with((string) $mime, 'video/') => 'video',
            default => 'document',
        };

        $mime ??= match ($kind) {
            'image' => 'image/jpeg',
            'audio' => $type === 'voice' ? 'audio/ogg' : 'audio/mpeg',
            'video' => 'video/mp4',
            default => 'application/octet-stream',
        };

        $base64 = base64_encode($contents);

        $attachment = match ($kind) {
            'image' => new Base64Image($base64, $mime),
            'audio' => new Base64Audio($base64, $mime),
            'video' => new Base64Video($base64, $mime),
            default => new Base64Document($base64, $mime),
        };

        return $name !== null ? $attachment->as($name) : $attachment;
    }

    /**
     * Read a file of a bot update.
     *
     * @return array{0: string, 1: string, 2: string|null, 3: string|null}
     */
    protected static function fromMediaFile(MediaFile $file): array
    {
        $file = $file->largest();

        return [static::download($file->url()), $file->type(), $file->mimeType(), $file->fileName()];
    }

    /**
     * Read the media of an MTProto update.
     *
     * @return array{0: string, 1: string, 2: string|null, 3: string|null}
     */
    protected static function fromClientRequest(\LaraGram\MTProto\Foundation\ClientRequest $request): array
    {
        $info = $request->getMediaInfo() ?? throw new InvalidArgumentException('The MTProto update has no media.');

        return [$request->downloadMedia(), (string) ($info['media_type'] ?? 'document'), $info['mime_type'] ?? null, $info['file_name'] ?? null];
    }

    /**
     * Download a file from the Bot API, or read it from a local Bot API server.
     */
    protected static function download(string $url): string
    {
        if (str_starts_with($url, 'file://')) {
            $contents = @file_get_contents(substr($url, 7));

            return $contents === false ? throw new RuntimeException('Unable to read the Telegram file.') : $contents;
        }

        $response = Http::timeout(300)->get($url);

        if (! $response->successful()) {
            throw new RuntimeException('Unable to download the Telegram file.');
        }

        return $response->body();
    }
}
