<?php

namespace LaraGram\Ai;

use LaraGram\Ai\Contracts\Agent;
use LaraGram\Ai\Contracts\Conversational;
use LaraGram\Ai\Contracts\HasTools;

/**
 * @phpstan-consistent-constructor
 */
class AnonymousAgent implements Agent, Conversational, HasTools
{
    use Promptable;

    public function __construct(public string $instructions, public iterable $messages, public iterable $tools) {}

    public function instructions(): string
    {
        return $this->instructions;
    }

    public function messages(): iterable
    {
        return $this->messages;
    }

    public function tools(): iterable
    {
        return $this->tools;
    }
}
