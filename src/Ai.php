<?php

namespace LaraGram\Ai;

use LaraGram\Support\Facades\Facade;

/**
 * @see AiManager
 *
 * @mixin AiManager
 */
class Ai extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return AiManager::class;
    }
}
