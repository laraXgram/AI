<?php

namespace LaraGram\Ai\Providers\Concerns;

use LaraGram\Ai\Contracts\Gateway\FileGateway;

trait HasFileGateway
{
    protected FileGateway $fileGateway;

    /**
     * Get the provider's file gateway.
     */
    public function fileGateway(): FileGateway
    {
        return $this->fileGateway;
    }

    /**
     * Set the provider's file gateway.
     */
    public function useFileGateway(FileGateway $gateway): self
    {
        $this->fileGateway = $gateway;

        return $this;
    }
}
