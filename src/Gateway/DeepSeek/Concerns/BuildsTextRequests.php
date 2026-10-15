<?php

namespace LaraGram\Ai\Gateway\DeepSeek\Concerns;

use LaraGram\Support\Arr;
use LaraGram\Ai\Gateway\Concerns\ComposesSchemaInstructions;
use LaraGram\Ai\Gateway\StepContext;
use LaraGram\Ai\Gateway\TextGenerationOptions;
use LaraGram\Ai\Providers\Provider;
use LaraGram\Ai\ToolChoice;

trait BuildsTextRequests
{
    use ComposesSchemaInstructions;

    /**
     * Build the request body for the current text generation step.
     */
    protected function buildStepBody(
        Provider $provider,
        string $model,
        ?string $instructions,
        array $messages,
        array $tools,
        ?array $schema,
        ?TextGenerationOptions $options,
        StepContext $stepContext,
    ): array {
        return $this->buildTextRequestBody($provider, $model, $instructions, $messages, $tools, $schema, $options);
    }

    /**
     * Build the request body for the Chat Completions API.
     */
    protected function buildTextRequestBody(
        Provider $provider,
        string $model,
        ?string $instructions,
        array $messages,
        array $tools,
        ?array $schema,
        ?TextGenerationOptions $options,
    ): array {
        $body = [
            'model' => $model,
            'messages' => $this->mapMessagesToChat(
                $messages,
                $this->composeInstructions($instructions, $schema),
            ),
        ];

        if (filled($tools)) {
            $mappedTools = $this->mapTools($tools, $provider);

            if (filled($mappedTools)) {
                $body['tool_choice'] = $options?->toolChoice instanceof ToolChoice
                    ? $this->mapToolChoice($options->toolChoice)
                    : 'auto';
                $body['tools'] = $mappedTools;
            }
        }

        if (filled($schema)) {
            $body['response_format'] = $this->buildResponseFormat();
        }

        if (! is_null($options?->maxTokens)) {
            $body['max_completion_tokens'] = $options->maxTokens;
        }

        $body = array_merge($body, Arr::whereNotNull([
            'temperature' => $options?->temperature,
            'top_p' => $options?->topP,
        ]));

        $providerOptions = $options?->providerOptions($provider->driver());

        if (filled($providerOptions)) {
            return array_merge($body, $providerOptions);
        }

        return $body;
    }

    /**
     * Build the response format options for structured output.
     */
    protected function buildResponseFormat(): array
    {
        return ['type' => 'json_object'];
    }
}
