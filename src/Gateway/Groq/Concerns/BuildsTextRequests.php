<?php

namespace LaraGram\Ai\Gateway\Groq\Concerns;

use LaraGram\Support\Arr;
use LaraGram\Ai\Attributes\Strict;
use LaraGram\Ai\Gateway\Concerns\ComposesSchemaInstructions;
use LaraGram\Ai\Gateway\StepContext;
use LaraGram\Ai\Gateway\TextGenerationOptions;
use LaraGram\Ai\ObjectSchema;
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
        $hasTools = false;

        $body = ['model' => $model];

        if (filled($tools)) {
            $mappedTools = $this->mapTools($tools, $provider);

            if (filled($mappedTools)) {
                $body['tool_choice'] = $options?->toolChoice instanceof ToolChoice
                    ? $this->mapToolChoice($options->toolChoice)
                    : 'auto';
                $body['tools'] = $mappedTools;
                $hasTools = true;
            }
        }

        $inlineSchema = $hasTools && filled($schema);

        $body['messages'] = $this->mapMessagesToChat(
            $messages,
            $inlineSchema ? $this->composeInstructions($instructions, $schema) : $instructions,
        );

        if (filled($schema) && ! $inlineSchema) {
            $body['response_format'] = $this->buildResponseFormat($schema, Strict::isAppliedTo($options?->agent));
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
    protected function buildResponseFormat(array $schema, bool $strict): array
    {
        $objectSchema = new ObjectSchema($schema, strict: $strict);

        $schemaArray = $objectSchema->toSchema();

        return [
            'type' => 'json_schema',
            'json_schema' => [
                'name' => $schemaArray['name'] ?? 'schema_definition',
                'schema' => Arr::except($schemaArray, ['name']),
                'strict' => $strict,
            ],
        ];
    }
}
