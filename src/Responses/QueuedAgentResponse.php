<?php

namespace LaraGram\Ai\Responses;

use LaraGram\Foundation\Bus\PendingDispatch;

/**
 * @mixin PendingDispatch
 */
class QueuedAgentResponse implements \LaraGram\Contracts\Support\Responsable
{
    use Concerns\HasQueuedResponseCallbacks, Concerns\SendsToTelegramWhenReady;

    public function __construct(protected PendingDispatch $dispatchable) {}

    /**
     * Proxy missing method calls to the pending dispatch instance.
     */
    public function __call(string $method, array $arguments)
    {
        return $this->dispatchable->{$method}(...$arguments);
    }
}
