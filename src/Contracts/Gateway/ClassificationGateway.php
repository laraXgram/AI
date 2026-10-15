<?php

namespace LaraGram\Ai\Contracts\Gateway;

use LaraGram\Http\UploadedFile;
use LaraGram\Ai\Contracts\Providers\ClassificationProvider;
use LaraGram\Ai\Contracts\Question;
use LaraGram\Ai\Files\File;
use LaraGram\Ai\Responses\ClassificationResponse;

interface ClassificationGateway
{
    /**
     * Answer the given questions about the state.
     *
     * @param  string|array<string, mixed>  $state
     * @param  array<string, Question>  $questions
     * @param  array<string, mixed>  $providerOptions
     * @param  array<int, File|UploadedFile>  $attachments
     */
    public function classify(
        ClassificationProvider $provider,
        string $model,
        string|array $state,
        array $questions,
        int $timeout = 30,
        array $providerOptions = [],
        array $attachments = [],
    ): ClassificationResponse;
}
