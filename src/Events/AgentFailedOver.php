<?php

namespace LaraGram\Ai\Events;

use LaraGram\Ai\Contracts\Agent;
use LaraGram\Ai\Exceptions\FailoverableException;
use LaraGram\Ai\Providers\Provider;

class AgentFailedOver extends ProviderFailedOver
{
    public function __construct(
        public string $invocationId,
        public Agent $agent,
        public Provider $provider,
        public string $model,
        public FailoverableException $exception) {}
}
