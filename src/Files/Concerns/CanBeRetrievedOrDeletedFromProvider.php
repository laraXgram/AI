<?php

namespace LaraGram\Ai\Files\Concerns;

use LaraGram\Ai\Files;
use LaraGram\Ai\Responses\FileResponse;

trait CanBeRetrievedOrDeletedFromProvider
{
    /**
     * Store the file on a given provider.
     */
    public function get(?string $provider = null): FileResponse
    {
        return Files::get($this->id, provider: $provider);
    }

    /**
     * Delete the file on a given provider.
     */
    public function delete(?string $provider = null): void
    {
        Files::delete($this->id, provider: $provider);
    }
}
