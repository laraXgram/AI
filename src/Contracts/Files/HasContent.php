<?php

namespace LaraGram\Ai\Contracts\Files;

use Stringable;

interface HasContent extends Stringable
{
    /**
     * Get the file's raw content.
     */
    public function content(): string;
}
