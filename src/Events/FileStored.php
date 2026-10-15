<?php

namespace LaraGram\Ai\Events;

use LaraGram\Ai\Contracts\Files\StorableFile;
use LaraGram\Ai\Providers\Provider;
use LaraGram\Ai\Responses\StoredFileResponse;

class FileStored
{
    public function __construct(
        public string $invocationId,
        public Provider $provider,
        public StorableFile $file,
        public StoredFileResponse $response,
    ) {}
}
