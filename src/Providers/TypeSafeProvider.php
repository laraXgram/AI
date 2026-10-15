<?php

namespace LaraGram\Ai\Providers;

use LaraGram\Contracts\Events\Dispatcher;
use LaraGram\Ai\Contracts\Gateway\ClassificationGateway;
use LaraGram\Ai\Contracts\Providers\ClassificationProvider;
use LaraGram\Ai\Gateway\TypeSafeGateway;

class TypeSafeProvider extends Provider implements ClassificationProvider
{
    use Concerns\Classifies;
    use Concerns\HasClassificationGateway;

    public function __construct(
        protected array $config,
        protected Dispatcher $events,
    ) {}

    /**
     * Get the name of the default classification model.
     */
    public function defaultClassificationModel(): string
    {
        return $this->config['models']['classification']['default'] ?? 'jev-latest';
    }

    /**
     * Get the provider's classification gateway.
     */
    public function classificationGateway(): ClassificationGateway
    {
        return $this->classificationGateway ??= new TypeSafeGateway;
    }
}
