<?php

namespace LaraGram\Ai\Events;

use LaraGram\Ai\Contracts\Agent;
use LaraGram\Ai\Contracts\Providers\TextProvider;
use Throwable;

class StepFailed
{
    /**
     * @param  Throwable  $exception  A StreamErrorException carries the provider's own error event on ->error.
     * @param  float  $time  Wall time spent in the provider call before it failed, in milliseconds.
     */
    public function __construct(
        public string $invocationId,
        public int $stepNumber,
        public Agent $agent,
        public TextProvider $provider,
        public string $model,
        public bool $isFinalStep,
        public Throwable $exception,
        public float $time,
    ) {}
}
