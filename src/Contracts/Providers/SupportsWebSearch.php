<?php

namespace LaraGram\Ai\Contracts\Providers;

use LaraGram\Ai\Providers\Tools\WebSearch;

interface SupportsWebSearch
{
    /**
     * Get the web search tool options for the provider.
     */
    public function webSearchToolOptions(WebSearch $search): array;
}
