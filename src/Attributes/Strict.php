<?php

namespace LaraGram\Ai\Attributes;

use Attribute;
use ReflectionClass;

#[Attribute(Attribute::TARGET_CLASS)]
final class Strict
{
    public static function isAppliedTo(?object $target): bool
    {
        return $target !== null
            && (new ReflectionClass($target))->getAttributes(self::class) !== [];
    }
}
