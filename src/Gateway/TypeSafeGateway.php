<?php

namespace LaraGram\Ai\Gateway;

use LaraGram\Http\Client\PendingRequest;
use LaraGram\Ai\Contracts\Gateway\ClassificationGateway;
use LaraGram\Ai\Contracts\Providers\ClassificationProvider;
use LaraGram\Ai\Gateway\Concerns\AnswersQuestions;
use LaraGram\Ai\Gateway\Concerns\HandlesFailoverErrors;

class TypeSafeGateway implements ClassificationGateway
{
    use AnswersQuestions;
    use Concerns\CreatesClient;
    use HandlesFailoverErrors;

    /**
     * Get the path of the endpoint that answers questions.
     */
    protected function classificationEndpoint(): string
    {
        return '/systemone';
    }

    /**
     * Get the answering model, preferring the checkpoint a routing server such as laya-serve chose.
     */
    protected function answeringModel(array $data, string $model): string
    {
        return $data['routing']['model'] ?? $data['model'] ?? $model;
    }

    /**
     * Get an HTTP client for the TypeSafe API.
     */
    protected function client(ClassificationProvider $provider, int $timeout = 30): PendingRequest
    {
        return $this->createClient(
            rtrim($provider->additionalConfiguration()['url'] ?? 'https://api.typesafe.ai/v1', '/'),
            [
                'Authorization' => 'Bearer '.$provider->providerCredentials()['key'],
                'Content-Type' => 'application/json',
            ],
            $provider->additionalConfiguration()['headers'] ?? [],
            $timeout,
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function overloadedStatusCodes(): array
    {
        return [529, 502, 503, 504, 520, 522, 524];
    }
}
