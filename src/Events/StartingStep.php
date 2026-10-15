<?php

namespace LaraGram\Ai\Events;

use LaraGram\Ai\Contracts\Agent;
use LaraGram\Ai\Contracts\Providers\TextProvider;
use LaraGram\Ai\Gateway\TextGenerationOptions;
use LaraGram\Ai\Messages\Message;

class StartingStep
{
    /**
     * @param  string  $model  The model the step is requested against, which the responding model reported on the completed step may differ from.
     * @param  Message[]  $messages  The messages being sent for this step, including the tool results of the steps before it.
     * @param  TextGenerationOptions|null  $options  The options resolved for this step, which may differ from the agent's own once a forced tool choice has been satisfied.
     */
    public function __construct(
        public string $invocationId,
        public int $stepNumber,
        public Agent $agent,
        public TextProvider $provider,
        public string $model,
        public bool $isFinalStep,
        public array $messages,
        public ?TextGenerationOptions $options,
    ) {}
}
