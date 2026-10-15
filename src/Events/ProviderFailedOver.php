<?php

namespace LaraGram\Ai\Events;

use LaraGram\Ai\Exceptions\FailoverableException;
use LaraGram\Ai\Providers\Provider;

class ProviderFailedOver
{
    public function __construct(
        public Provider $provider,
        public string $model,
        public FailoverableException $exception) {}
}
