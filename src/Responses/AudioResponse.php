<?php

namespace LaraGram\Ai\Responses;

use LaraGram\Ai\Responses\Concerns\SendsToTelegram;
use LaraGram\Ai\Telegram\Chat;
use LaraGram\Ai\Telegram\Sender;
use LaraGram\Ai\Telegram\Upload;
use LaraGram\Contracts\Support\Responsable;
use LaraGram\Support\Str;
use LaraGram\Ai\Concerns\Storable;
use LaraGram\Ai\Responses\Data\Meta;
use LaraGram\Ai\Responses\Data\Usage;

class AudioResponse implements Responsable, \Stringable
{
    use SendsToTelegram, Storable;

    public ?string $mime = null;

    /**
     * @param  string  $audio  The Base64 representation of the audio.
     */
    public function __construct(
        public string $audio,
        public Usage $usage,
        public Meta $meta,
        ?string $mimeType = null,
    ) {
        $this->mime = $mimeType;
    }

    /**
     * Get a default filename for the file.
     */
    protected function randomStorageName(): string
    {
        return once(fn (): string => Str::random(40).match ($this->mime) {
            'audio/wav', 'audio/x-wav' => '.wav',
            'audio/opus' => '.opus',
            'audio/pcm' => '.pcm',
            'audio/ulaw' => '.ulaw',
            'audio/alaw' => '.alaw',
            default => '.mp3',
        });
    }

    /**
     * Get the raw representation of the audio.
     */
    public function content(): string
    {
        return base64_decode($this->audio);
    }

    /**
     * Get the MIME type for the audio.
     */
    public function mimeType(): ?string
    {
        return $this->mime;
    }

    /**
     * Get the raw string content of the audio.
     */
    public function __toString(): string
    {
        return $this->content();
    }

    /**
     * Send the audio to the given chat as a voice message.
     *
     * Telegram shows OGG/Opus, MP3 and M4A audio as a voice message; other formats are sent as an audio file.
     */
    protected function deliverTo(Chat $chat): void
    {
        $sender = Sender::to($chat);

        $sender->answerCallback();

        $mime = $this->mime ?? 'audio/mpeg';

        $extension = match (true) {
            str_contains($mime, 'ogg'), str_contains($mime, 'opus') => 'ogg',
            str_contains($mime, 'mp4'), str_contains($mime, 'm4a'), str_contains($mime, 'aac') => 'm4a',
            str_contains($mime, 'wav') => 'wav',
            str_contains($mime, 'flac') => 'flac',
            default => 'mp3',
        };

        $upload = new Upload($this->content(), 'voice.'.$extension, $mime);

        in_array($extension, ['ogg', 'mp3', 'm4a'], true)
            ? $sender->call('sendVoice', [...$chat->parameters(), 'voice' => $upload])
            : $sender->call('sendAudio', [...$chat->parameters(), 'audio' => $upload]);
    }
}
