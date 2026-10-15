<?php

namespace LaraGram\Ai\Events;

use LaraGram\Ai\Contracts\Files\StorableFile;
use LaraGram\Ai\Providers\Provider;
use Throwable;

class StoringFileFailed
{
    public function __construct(
        public string $invocationId,
        public Provider $provider,
        public StorableFile $file,
        public Throwable $exception,
    ) {}
}
