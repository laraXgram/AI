<?php

namespace LaraGram\Ai\Events;

use LaraGram\Ai\Contracts\Agent;
use LaraGram\Ai\Contracts\Providers\TextProvider;
use LaraGram\Ai\Gateway\StepResponse;

class StepCompleted
{
    /**
     * @param  string  $model  The model the step was requested against, which the responding model reported in $response->meta may differ from.
     * @param  float  $time  Wall time spent in the provider call, in milliseconds.
     */
    public function __construct(
        public string $invocationId,
        public int $stepNumber,
        public Agent $agent,
        public TextProvider $provider,
        public string $model,
        public bool $isFinalStep,
        public StepResponse $response,
        public float $time,
    ) {}
}
