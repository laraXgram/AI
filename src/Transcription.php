<?php

namespace LaraGram\Ai;

use LaraGram\Ai\Files\TelegramFile;
use Closure;
use LaraGram\Http\UploadedFile;
use InvalidArgumentException;
use LaraGram\Ai\Contracts\Files\TranscribableAudio;
use LaraGram\Ai\Files\Base64Audio;
use LaraGram\Ai\Files\LocalAudio;
use LaraGram\Ai\Files\StoredAudio;
use LaraGram\Ai\Gateway\FakeTranscriptionGateway;
use LaraGram\Ai\PendingResponses\PendingTranscriptionGeneration;

class Transcription
{
    /**
     * Generate a transcription of the given audio.
     *
     * @throws InvalidArgumentException if the given base64 audio string is empty.
     */
    public static function of(mixed $audio): PendingTranscriptionGeneration
    {
        if (TelegramFile::is($audio)) {
            $audio = Base64Audio::fromUpload($audio);
        } elseif (is_string($audio)) {
            $audio = new Base64Audio($audio);
        } elseif ($audio instanceof UploadedFile) {
            $audio = Base64Audio::fromUpload($audio);
        }

        return new PendingTranscriptionGeneration($audio);
    }

    /**
     * Generate a transcription of the given audio.
     *
     * @throws InvalidArgumentException if the given base64 audio string is empty.
     */
    public static function fromBase64(string $base64, ?string $mimeType = null): PendingTranscriptionGeneration
    {
        return static::of(new Base64Audio($base64, $mimeType));
    }

    /**
     * Generate a transcription of the audio at the given path.
     */
    public static function fromPath(string $path, ?string $mimeType = null): PendingTranscriptionGeneration
    {
        return static::of(new LocalAudio($path, $mimeType));
    }

    /**
     * Generate a transcription of the given stored audio.
     */
    public static function fromStorage(string $path, ?string $disk = null): PendingTranscriptionGeneration
    {
        return static::of(new StoredAudio($path, $disk));
    }

    /**
     * Generate a transcription of the given uploaded file.
     */
    public static function fromUpload(mixed $file): PendingTranscriptionGeneration
    {
        return static::of($file);
    }

    /**
     * Fake transcription generation.
     */
    public static function fake(Closure|array $responses = []): FakeTranscriptionGateway
    {
        return Ai::fakeTranscriptions($responses);
    }

    /**
     * Determine if transcription generation is faked.
     */
    public static function isFaked(): bool
    {
        return Ai::transcriptionsAreFaked();
    }
}
