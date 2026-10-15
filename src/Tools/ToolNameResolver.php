<?php

namespace LaraGram\Ai\Tools;

use LaraGram\Ai\Contracts\Tool;
use LaraGram\Ai\Providers\Tools\ProviderTool;

class ToolNameResolver
{
    public static function resolve(Tool|ProviderTool $tool): string
    {
        return is_callable([$tool, 'name']) ? $tool->name() : class_basename($tool);
    }
}
