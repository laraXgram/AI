<?php

namespace LaraGram\Ai\Concerns;

use Closure;
use LaraGram\Support\Collection;
use LaraGram\Ai\Gateway\FakeClassificationGateway;
use LaraGram\Ai\Prompts\ClassificationPrompt;

trait InteractsWithFakeClassification
{
    /**
     * The fake classification gateway instance.
     */
    protected ?FakeClassificationGateway $fakeClassificationGateway = null;

    /**
     * All of the recorded classifications.
     */
    protected array $recordedClassifications = [];

    /**
     * Fake classification operations.
     */
    public function fakeClassification(Closure|array $responses = []): FakeClassificationGateway
    {
        return $this->fakeClassificationGateway = new FakeClassificationGateway($responses);
    }

    /**
     * Record a classification.
     */
    public function recordClassification(ClassificationPrompt $prompt): self
    {
        $this->recordedClassifications[] = $prompt;

        return $this;
    }

    /**
     * Determine if classification is faked.
     */
    public function classificationIsFaked(): bool
    {
        return $this->fakeClassificationGateway !== null;
    }

    /**
     * Get the fake classification gateway.
     */
    public function fakeClassificationGateway(): ?FakeClassificationGateway
    {
        return $this->fakeClassificationGateway;
    }
}
