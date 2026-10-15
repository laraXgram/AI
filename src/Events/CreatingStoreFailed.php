<?php

namespace LaraGram\Ai\Events;

use DateInterval;
use LaraGram\Support\Collection;
use LaraGram\Ai\Providers\Provider;
use Throwable;

class CreatingStoreFailed
{
    public function __construct(
        public string $invocationId,
        public Provider $provider,
        public string $name,
        public ?string $description,
        public Collection $fileIds,
        public ?DateInterval $expiresWhenIdleFor,
        public Throwable $exception,
    ) {}
}
