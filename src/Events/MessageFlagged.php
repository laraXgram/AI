<?php

namespace LaraGram\Ai\Events;

use LaraGram\Request\Request;

class MessageFlagged
{
    /**
     * Create a new event instance.
     *
     * @param  array<string, float>  $categories  The flagged categories and their probabilities.
     */
    public function __construct(
        public Request $request,
        public array $categories,
        public string $action,
    ) {}
}
