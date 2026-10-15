<?php

namespace LaraGram\Ai\Events;

use LaraGram\Ai\Contracts\Files\StorableFile;
use LaraGram\Ai\Providers\Provider;

class StoringFile
{
    public function __construct(
        public string $invocationId,
        public Provider $provider,
        public StorableFile $file,
    ) {}
}
