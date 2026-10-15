<?php

namespace LaraGram\Ai;

use Closure;
use LaraGram\Http\UploadedFile;
use InvalidArgumentException;
use LaraGram\Ai\Files\File;
use LaraGram\Ai\Gateway\FakeClassificationGateway;
use LaraGram\Ai\PendingResponses\PendingClassification;

class Classification
{
    /**
     * Create a new pending classification for the given state.
     *
     * @param  string|array<string, mixed>  $state
     * @param  array<int, File|UploadedFile>  $attachments
     *
     * @throws InvalidArgumentException if the state is blank.
     */
    public static function of(string|array $state, array $attachments = []): PendingClassification
    {
        return new PendingClassification($state, $attachments);
    }

    /**
     * Fake classification operations.
     */
    public static function fake(Closure|array $responses = []): FakeClassificationGateway
    {
        return Ai::fakeClassification($responses);
    }

    /**
     * Determine if classification is faked.
     */
    public static function isFaked(): bool
    {
        return Ai::classificationIsFaked();
    }
}
