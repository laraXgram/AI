<?php

namespace LaraGram\Ai\Providers\Concerns;

use LaraGram\Http\UploadedFile;
use LaraGram\Support\Str;
use LaraGram\Ai\Ai;
use LaraGram\Ai\Contracts\Question;
use LaraGram\Ai\Events\ClassificationFailed;
use LaraGram\Ai\Events\Classified;
use LaraGram\Ai\Events\Classifying;
use LaraGram\Ai\Files\File;
use LaraGram\Ai\Prompts\ClassificationPrompt;
use LaraGram\Ai\Responses\ClassificationResponse;
use LogicException;
use Throwable;

trait Classifies
{
    /**
     * Answer the given questions about the state.
     *
     * @param  string|array<string, mixed>  $state
     * @param  array<string, Question>  $questions
     * @param  array<string, mixed>  $providerOptions
     * @param  array<int, File|UploadedFile>  $attachments
     */
    public function classify(string|array $state, array $questions, ?string $model = null, int $timeout = 30, array $providerOptions = [], array $attachments = []): ClassificationResponse
    {
        if ($attachments !== [] && ! $this->supportsClassificationAttachments()) {
            throw new LogicException("Provider [{$this->name()}] does not support classification attachments.");
        }

        $invocationId = (string) Str::uuid7();

        $model ??= $this->defaultClassificationModel();

        $prompt = new ClassificationPrompt($state, $questions, $this, $model, $timeout, $providerOptions, $attachments);

        if (Ai::classificationIsFaked()) {
            Ai::recordClassification($prompt);
        }

        $this->events->dispatch(new Classifying(
            $invocationId, $this, $model, $prompt,
        ));

        return tap($this->dispatchingFailure(
            fn () => $this->classificationGateway()->classify(
                $this,
                $model,
                $state,
                $questions,
                $timeout,
                $providerOptions,
                $attachments,
            ),
            fn (Throwable $exception) => new ClassificationFailed($invocationId, $this, $model, $prompt, $exception),
        ), fn (ClassificationResponse $response) => $this->events->dispatch(new Classified(
            $invocationId, $this, $model, $prompt, $response,
        )));
    }

    /**
     * Determine if the provider can classify attachments alongside the state.
     */
    public function supportsClassificationAttachments(): bool
    {
        return false;
    }
}
