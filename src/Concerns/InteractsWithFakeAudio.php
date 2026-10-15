<?php

namespace LaraGram\Ai\Concerns;

use Closure;
use LaraGram\Support\Collection;
use LaraGram\Ai\Gateway\FakeAudioGateway;
use LaraGram\Ai\Prompts\AudioPrompt;
use LaraGram\Ai\Prompts\QueuedAudioPrompt;

trait InteractsWithFakeAudio
{
    /**
     * The fake audio gateway instance.
     */
    protected ?FakeAudioGateway $fakeAudioGateway = null;

    /**
     * All of the recorded audio generations.
     */
    protected array $recordedAudioGenerations = [];

    /**
     * All of the recorded audio generations that were queued.
     */
    protected array $recordedQueuedAudioGenerations = [];

    /**
     * Fake audio generation.
     */
    public function fakeAudio(Closure|array $responses = []): FakeAudioGateway
    {
        return $this->fakeAudioGateway = new FakeAudioGateway($responses);
    }

    /**
     * Record an audio generation.
     */
    public function recordAudioGeneration(AudioPrompt|QueuedAudioPrompt $prompt): self
    {
        if ($prompt instanceof QueuedAudioPrompt) {
            $this->recordedQueuedAudioGenerations[] = $prompt;
        } else {
            $this->recordedAudioGenerations[] = $prompt;
        }

        return $this;
    }

    /**
     * Determine if audio generation is faked.
     */
    public function audioIsFaked(): bool
    {
        return $this->fakeAudioGateway !== null;
    }

    /**
     * Get the fake audio gateway.
     */
    public function fakeAudioGateway(): ?FakeAudioGateway
    {
        return $this->fakeAudioGateway;
    }
}
