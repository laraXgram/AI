<?php

namespace LaraGram\Ai\Contracts\Files;

interface HasName
{
    /**
     * Get the displayable name of the file.
     */
    public function name(): ?string;

    /**
     * Set the displayable name of the file.
     */
    public function as(?string $name): static;
}
