<?php

namespace LaraGram\Ai\Gateway\AzureOpenAi;

use LaraGram\Ai\Enums\Lab;
use LaraGram\Ai\Gateway\OpenAi\OpenAiFileGateway;

class AzureOpenAiFileGateway extends OpenAiFileGateway
{
    use Concerns\CreatesAzureOpenAiClient;

    /**
     * Get the default purpose to use when a file does not specify one.
     */
    #[\Override]
    protected function defaultPurpose(): string
    {
        return 'assistants';
    }

    /**
     * Get the provider key used to resolve file upload options.
     */
    #[\Override]
    protected function providerOptionsKey(): Lab
    {
        return Lab::Azure;
    }
}
