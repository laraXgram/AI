<?php

namespace LaraGram\Ai\Concerns;

use Closure;
use LaraGram\Support\Collection;
use LaraGram\Ai\Gateway\FakeTranscriptionGateway;
use LaraGram\Ai\Prompts\QueuedTranscriptionPrompt;
use LaraGram\Ai\Prompts\TranscriptionPrompt;

trait InteractsWithFakeTranscriptions
{
    /**
     * The fake transcription gateway instance.
     */
    protected ?FakeTranscriptionGateway $fakeTranscriptionGateway = null;

    /**
     * All of the recorded transcription generations.
     */
    protected array $recordedTranscriptionGenerations = [];

    /**
     * All of the recorded transcription generations that were queued.
     */
    protected array $recordedQueuedTranscriptionGenerations = [];

    /**
     * Fake transcription generation.
     */
    public function fakeTranscriptions(Closure|array $responses = []): FakeTranscriptionGateway
    {
        return $this->fakeTranscriptionGateway = new FakeTranscriptionGateway($responses);
    }

    /**
     * Record a transcription generation.
     */
    public function recordTranscriptionGeneration(TranscriptionPrompt|QueuedTranscriptionPrompt $prompt): self
    {
        if ($prompt instanceof QueuedTranscriptionPrompt) {
            $this->recordedQueuedTranscriptionGenerations[] = $prompt;
        } else {
            $this->recordedTranscriptionGenerations[] = $prompt;
        }

        return $this;
    }

    /**
     * Determine if transcription generation is faked.
     */
    public function transcriptionsAreFaked(): bool
    {
        return $this->fakeTranscriptionGateway !== null;
    }

    /**
     * Get the fake transcription gateway.
     */
    public function fakeTranscriptionGateway(): ?FakeTranscriptionGateway
    {
        return $this->fakeTranscriptionGateway;
    }
}
