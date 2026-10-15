<?php

namespace LaraGram\Ai\Attributes;

use Attribute;
use LaraGram\Ai\Enums\Lab;

#[Attribute(Attribute::TARGET_CLASS)]
class Provider
{
    public function __construct(public Lab|array|string $value)
    {
        //
    }
}
