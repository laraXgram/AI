<?php

namespace LaraGram\Ai\Gateway\AzureOpenAi;

use LaraGram\Ai\Gateway\OpenAi\OpenAiStoreGateway;

class AzureOpenAiStoreGateway extends OpenAiStoreGateway
{
    use Concerns\CreatesAzureOpenAiClient;
}
