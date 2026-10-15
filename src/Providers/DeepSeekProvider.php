<?php

namespace LaraGram\Ai\Providers;

use LaraGram\Contracts\Events\Dispatcher;
use LaraGram\Ai\Contracts\Gateway\StepTextGateway;
use LaraGram\Ai\Contracts\Providers\TextProvider;
use LaraGram\Ai\Gateway\DeepSeek\DeepSeekGateway;

class DeepSeekProvider extends Provider implements TextProvider
{
    use Concerns\GeneratesText;
    use Concerns\HasTextGateway;
    use Concerns\StreamsText;

    public function __construct(protected array $config, protected Dispatcher $events)
    {
        //
    }

    /**
     * Get the provider's text gateway.
     */
    public function textGateway(): StepTextGateway
    {
        return $this->textGateway ??= new DeepSeekGateway($this->events);
    }

    /**
     * Get the name of the default text model.
     */
    public function defaultTextModel(): string
    {
        return $this->config['models']['text']['default'] ?? 'deepseek-flash';
    }

    /**
     * Get the name of the cheapest text model.
     */
    public function cheapestTextModel(): string
    {
        return $this->config['models']['text']['cheapest'] ?? 'deepseek-flash';
    }

    /**
     * Get the name of the smartest text model.
     */
    public function smartestTextModel(): string
    {
        return $this->config['models']['text']['smartest'] ?? 'deepseek-v4-pro';
    }
}
