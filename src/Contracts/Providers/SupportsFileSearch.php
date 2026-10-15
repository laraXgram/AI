<?php

namespace LaraGram\Ai\Contracts\Providers;

use LaraGram\Ai\Providers\Tools\FileSearch;

interface SupportsFileSearch
{
    /**
     * Get the file search tool options for the provider.
     */
    public function fileSearchToolOptions(FileSearch $search): array;
}
