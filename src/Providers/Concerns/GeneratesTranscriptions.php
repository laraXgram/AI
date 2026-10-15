<?php

namespace LaraGram\Ai\Providers\Concerns;

use LaraGram\Support\Str;
use LaraGram\Ai\Ai;
use LaraGram\Ai\Contracts\Files\TranscribableAudio;
use LaraGram\Ai\Events\GeneratingTranscription;
use LaraGram\Ai\Events\TranscriptionFailed;
use LaraGram\Ai\Events\TranscriptionGenerated;
use LaraGram\Ai\Prompts\TranscriptionPrompt;
use LaraGram\Ai\Responses\TranscriptionResponse;
use Throwable;

trait GeneratesTranscriptions
{
    /**
     * Transcribe the given audio to text.
     *
     * @param  array<string, mixed>  $providerOptions
     */
    public function transcribe(
        TranscribableAudio $audio,
        ?string $language = null,
        bool $diarize = false,
        ?string $model = null,
        ?int $timeout = null,
        array $providerOptions = [],
    ): TranscriptionResponse {
        $invocationId = (string) Str::uuid7();

        $model ??= $this->defaultTranscriptionModel();

        $prompt = new TranscriptionPrompt($audio, $language, $diarize, $this, $model, $timeout, $providerOptions);

        if (Ai::transcriptionsAreFaked()) {
            Ai::recordTranscriptionGeneration($prompt);
        }

        $this->events->dispatch(new GeneratingTranscription(
            $invocationId, $this, $model, $prompt,
        ));

        return tap($this->dispatchingFailure(
            fn () => $this->transcriptionGateway()->generateTranscription(
                $this, $model, $prompt->audio, $prompt->language, $prompt->diarize, $prompt->timeout ?? 30, $prompt->providerOptions
            ),
            fn (Throwable $exception) => new TranscriptionFailed($invocationId, $this, $model, $prompt, $exception),
        ), function (TranscriptionResponse $response) use ($invocationId, $model, $prompt): void {
            $this->events->dispatch(new TranscriptionGenerated(
                $invocationId, $this, $model, $prompt, $response
            ));
        });
    }
}
