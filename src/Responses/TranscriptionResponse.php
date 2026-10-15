<?php

namespace LaraGram\Ai\Responses;

use LaraGram\Ai\Responses\Concerns\SendsToTelegram;
use LaraGram\Ai\Telegram\Chat;
use LaraGram\Ai\Telegram\Sender;
use LaraGram\Contracts\Support\Responsable;
use LaraGram\Support\Collection;
use LaraGram\Ai\Responses\Data\Meta;
use LaraGram\Ai\Responses\Data\TranscriptionSegment;
use LaraGram\Ai\Responses\Data\TranscriptionUsage;

class TranscriptionResponse implements Responsable, \Stringable
{
    use SendsToTelegram;

    public string $text;

    /** @var Collection<int, TranscriptionSegment> */
    public Collection $segments;

    public TranscriptionUsage $usage;

    public Meta $meta;

    /**
     * @param  Collection<int, TranscriptionSegment>  $segments
     */
    public function __construct(
        string $text,
        Collection $segments,
        TranscriptionUsage $usage,
        Meta $meta,
    ) {
        $this->text = $text;
        $this->segments = $segments;
        $this->usage = $usage;
        $this->meta = $meta;
    }

    /**
     * Get the string representation of the transcription.
     */
    public function __toString(): string
    {
        return $this->text;
    }

    /**
     * Send the transcript to the given chat.
     */
    protected function deliverTo(Chat $chat): void
    {
        Sender::to($chat)->markdown(trim($this->text) === '' ? '…' : $this->text, format: 'html');
    }
}
