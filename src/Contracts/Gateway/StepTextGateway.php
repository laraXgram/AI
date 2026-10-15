<?php

namespace LaraGram\Ai\Contracts\Gateway;

use Generator;
use LaraGram\JsonSchema\Types\Type;
use LaraGram\Ai\Contracts\Providers\TextProvider;
use LaraGram\Ai\Contracts\Tool;
use LaraGram\Ai\Gateway\StepContext;
use LaraGram\Ai\Gateway\StepResponse;
use LaraGram\Ai\Gateway\TextGenerationOptions;
use LaraGram\Ai\Messages\Message;
use LaraGram\Ai\Streaming\Events\StreamEvent;

interface StepTextGateway
{
    /**
     * Generate text for a single step in a conversation.
     *
     * @param  Message[]  $messages
     * @param  Tool[]  $tools
     * @param  array<string, Type>|null  $schema
     */
    public function generateTextStep(
        TextProvider $provider,
        string $model,
        ?string $instructions,
        array $messages,
        array $tools,
        ?array $schema,
        ?TextGenerationOptions $options,
        ?int $timeout,
        StepContext $stepContext,
    ): StepResponse;

    /**
     * Stream text for a single step in a conversation.
     *
     * @param  Message[]  $messages
     * @param  Tool[]  $tools
     * @param  array<string, Type>|null  $schema
     * @return Generator<int, StreamEvent, mixed, StepResponse|null>
     */
    public function generateStreamStep(
        string $invocationId,
        TextProvider $provider,
        string $model,
        ?string $instructions,
        array $messages,
        array $tools,
        ?array $schema,
        ?TextGenerationOptions $options,
        ?int $timeout,
        StepContext $stepContext,
    ): Generator;
}
