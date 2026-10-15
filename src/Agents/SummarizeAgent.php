<?php

namespace LaraGram\Ai\Agents;

use LaraGram\Support\Str;
use LaraGram\Ai\Attributes\UseCheapestModel;
use LaraGram\Ai\Contracts\Agent;
use LaraGram\Ai\Promptable;

#[UseCheapestModel]
final class SummarizeAgent implements Agent
{
    use Promptable;

    protected int $sentences;

    public function __construct(int $sentences = 3)
    {
        $this->sentences = max(1, $sentences);
    }

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): string
    {
        return sprintf(
            'Summarize the given text in no more than %d %s. Respond with only the summary and nothing else.',
            $this->sentences,
            Str::plural('sentence', $this->sentences),
        );
    }
}
