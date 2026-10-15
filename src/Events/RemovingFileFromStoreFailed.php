<?php

namespace LaraGram\Ai\Events;

use LaraGram\Ai\Providers\Provider;
use Throwable;

class RemovingFileFromStoreFailed
{
    public function __construct(
        public string $invocationId,
        public Provider $provider,
        public string $storeId,
        public string $documentId,
        public Throwable $exception,
    ) {}
}
