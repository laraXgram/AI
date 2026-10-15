<?php

namespace LaraGram\Ai\Gateway\OpenAiCompatible;

use LaraGram\Ai\Gateway\OpenAi\OpenAiClassificationGateway;

class OpenAiCompatibleClassificationGateway extends OpenAiClassificationGateway
{
    use Concerns\CreatesOpenAiCompatibleClient;
}
