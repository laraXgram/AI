<?php

namespace LaraGram\Ai\Concerns;

use LaraGram\Support\Collection;

trait JoinsReasoning
{
    /**
     * Join reasoning blocks, separating each block with a blank line.
     *
     * @param  iterable<int, string>  $blocks
     */
    protected static function joinReasoning(iterable $blocks): string
    {
        return Collection::wrap($blocks)
            ->filter(fn (string $block): bool => trim($block) !== '')
            ->implode("\n\n");
    }
}
