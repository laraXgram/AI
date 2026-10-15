<?php

namespace LaraGram\Ai\Gateway\Bedrock;

use Generator;
use LaraGram\Filesystem\Aws\AwsClient;
use LaraGram\Filesystem\Aws\EventStream\EventStreamDecoder;
use LaraGram\Filesystem\Aws\Result;
use LaraGram\Http\Client\Core\PsrUtils;
use LaraGram\Http\Client\Core\Request;
use LaraGram\Http\Factory\ResponseInterface;
use stdClass;

class BedrockRuntimeClient extends AwsClient
{
    /**
     * The members whose values are binary blobs, sent base64 encoded.
     *
     * @var string[]
     */
    protected const BLOB_MEMBERS = ['bytes', 'redactedContent'];

    /**
     * The members that must be sent as an object even when empty.
     *
     * @var string[]
     */
    protected const STRUCTURE_MEMBERS = ['auto', 'any'];

    /**
     * Get the name of the service used for signing.
     *
     * @return string
     */
    protected function signingName(): string
    {
        return 'bedrock';
    }

    /**
     * Get the endpoint used when none is configured.
     *
     * @return string
     */
    protected function defaultEndpoint(): string
    {
        return "https://bedrock-runtime.{$this->region}.amazonaws.com";
    }

    /**
     * Get the exception class the client throws.
     *
     * @return class-string<\LaraGram\Ai\Gateway\Bedrock\BedrockRuntimeException>
     */
    protected function exceptionClass(): string
    {
        return BedrockRuntimeException::class;
    }

    /**
     * Send a message to a model through the Converse API.
     *
     * @param  array<string, mixed>  $parameters
     * @return \LaraGram\Filesystem\Aws\Result
     */
    public function converse(array $parameters): Result
    {
        $response = $this->sendJson('converse', $parameters);

        return new Result($this->decodeBlobs(json_decode((string) $response->getBody(), true) ?? []));
    }

    /**
     * Send a message to a model through the Converse API and stream its answer.
     *
     * The result's "stream" yields each event as an array keyed by the event type.
     *
     * @param  array<string, mixed>  $parameters
     * @return \LaraGram\Filesystem\Aws\Result
     */
    public function converseStream(array $parameters): Result
    {
        $response = $this->sendJson('converse-stream', $parameters, stream: true);

        return new Result(['stream' => $this->events($response)]);
    }

    /**
     * Invoke a model with its native request body.
     *
     * @param  array{modelId: string, body: string, contentType?: string, accept?: string}  $parameters
     * @return \LaraGram\Filesystem\Aws\Result
     */
    public function invokeModel(array $parameters): Result
    {
        $response = $this->sendRequest(new Request(
            'POST',
            $this->modelUri($parameters['modelId'], 'invoke'),
            [
                'Content-Type' => $parameters['contentType'] ?? 'application/json',
                'Accept' => $parameters['accept'] ?? 'application/json',
            ],
            $parameters['body'] ?? '',
        ));

        return new Result([
            'body' => PsrUtils::streamFor((string) $response->getBody()),
            'contentType' => $response->getHeaderLine('Content-Type'),
        ]);
    }

    /**
     * Send a JSON request to the given model operation.
     *
     * @param  string  $operation
     * @param  array<string, mixed>  $parameters
     * @param  bool  $stream
     * @return \LaraGram\Http\Factory\ResponseInterface
     */
    protected function sendJson(string $operation, array $parameters, bool $stream = false): ResponseInterface
    {
        $modelId = $parameters['modelId'];

        unset($parameters['modelId']);

        return $this->sendRequest(new Request(
            'POST',
            $this->modelUri($modelId, $operation),
            ['Content-Type' => 'application/json'],
            json_encode($this->encodeMembers($parameters), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        ), $stream);
    }

    /**
     * Get the URI of the given model operation.
     *
     * @param  string  $modelId
     * @param  string  $operation
     * @return \LaraGram\Http\Factory\UriInterface
     */
    protected function modelUri(string $modelId, string $operation)
    {
        return $this->endpoint->withPath('/model/'.rawurlencode($modelId).'/'.$operation);
    }

    /**
     * Decode the events of a streamed response.
     *
     * @param  \LaraGram\Http\Factory\ResponseInterface  $response
     * @return \Generator<int, array<string, mixed>>
     */
    protected function events(ResponseInterface $response): Generator
    {
        foreach ((new EventStreamDecoder)->decode($response->getBody()) as $message) {
            $headers = $message['headers'];
            $payload = $message['payload'] === '' ? [] : (json_decode($message['payload'], true) ?? []);

            if (($headers[':message-type'] ?? 'event') !== 'event') {
                $code = ucfirst((string) ($headers[':exception-type'] ?? $headers[':error-code'] ?? 'UnknownError'));

                throw new BedrockRuntimeException(
                    'Bedrock stream error ['.$code.']: '.($payload['message'] ?? $payload['Message'] ?? $headers[':error-message'] ?? ''),
                    $code,
                    $response->getStatusCode(),
                    $response->getHeaderLine('x-amzn-RequestId') ?: null,
                    $response,
                    $payload['message'] ?? $payload['Message'] ?? null,
                );
            }

            yield [(string) ($headers[':event-type'] ?? 'unknown') => $this->decodeBlobs($payload)];
        }
    }

    /**
     * Base64 encode the blob members and turn empty structures into objects.
     *
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    protected function encodeMembers(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_string($key) && in_array($key, static::BLOB_MEMBERS, true) && is_string($value)) {
                $data[$key] = base64_encode($value);
            } elseif (is_string($key) && in_array($key, static::STRUCTURE_MEMBERS, true) && $value === []) {
                $data[$key] = new stdClass;
            } elseif (is_array($value)) {
                $data[$key] = $this->encodeMembers($value);
            }
        }

        return $data;
    }

    /**
     * Base64 decode the blob members of a response.
     *
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    protected function decodeBlobs(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_string($key) && in_array($key, static::BLOB_MEMBERS, true) && is_string($value)) {
                $data[$key] = base64_decode($value, true) ?: $value;
            } elseif (is_array($value)) {
                $data[$key] = $this->decodeBlobs($value);
            }
        }

        return $data;
    }
}
