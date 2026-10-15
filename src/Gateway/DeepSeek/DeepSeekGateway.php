<?php

namespace LaraGram\Ai\Gateway\DeepSeek;

use LaraGram\Contracts\Events\Dispatcher;
use LaraGram\Ai\Contracts\Gateway\StepTextGateway;
use LaraGram\Ai\Gateway\Concerns\HandlesFailoverErrors;
use LaraGram\Ai\Gateway\Concerns\ParsesServerSentEvents;
use LaraGram\Ai\Gateway\OpenAiCompatible\Concerns\MapsChatCompletionTools;
use LaraGram\Ai\Gateway\OpenAiCompatible\Concerns\PerformsChatCompletionSteps;

class DeepSeekGateway implements StepTextGateway
{
    use Concerns\BuildsTextRequests;
    use Concerns\CreatesDeepSeekClient;
    use Concerns\HandlesTextStreaming;
    use Concerns\MapsAttachments;
    use Concerns\MapsMessages;
    use Concerns\ParsesTextResponses;
    use HandlesFailoverErrors;
    use MapsChatCompletionTools;
    use ParsesServerSentEvents;
    use PerformsChatCompletionSteps;

    public function __construct(protected Dispatcher $events)
    {
        //
    }
}
