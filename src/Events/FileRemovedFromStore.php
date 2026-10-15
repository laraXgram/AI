<?php

namespace LaraGram\Ai\Events;

use LaraGram\Ai\Providers\Provider;

class FileRemovedFromStore
{
    public function __construct(
        public string $invocationId,
        public Provider $provider,
        public string $storeId,
        public string $documentId,
    ) {}
}
