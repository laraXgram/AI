<?php

namespace LaraGram\Ai\Providers\Concerns;

use LaraGram\Ai\Contracts\Gateway\StoreGateway;

trait HasStoreGateway
{
    protected StoreGateway $storeGateway;

    /**
     * Get the provider's store gateway.
     */
    public function storeGateway(): StoreGateway
    {
        return $this->storeGateway;
    }

    /**
     * Set the provider's store gateway.
     */
    public function useStoreGateway(StoreGateway $gateway): self
    {
        $this->storeGateway = $gateway;

        return $this;
    }
}
