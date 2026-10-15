<?php

namespace LaraGram\Ai\Gateway\Mistral;

use LaraGram\Contracts\Events\Dispatcher;
use LaraGram\Ai\Contracts\Files\TranscribableAudio;
use LaraGram\Ai\Contracts\Gateway\AudioGateway;
use LaraGram\Ai\Contracts\Gateway\EmbeddingGateway;
use LaraGram\Ai\Contracts\Gateway\StepTextGateway;
use LaraGram\Ai\Contracts\Gateway\TranscriptionGateway;
use LaraGram\Ai\Contracts\Providers\AudioProvider;
use LaraGram\Ai\Contracts\Providers\EmbeddingProvider;
use LaraGram\Ai\Contracts\Providers\TextProvider;
use LaraGram\Ai\Contracts\Providers\TranscriptionProvider;
use LaraGram\Ai\Gateway\Concerns\HandlesFailoverErrors;
use LaraGram\Ai\Gateway\Concerns\ParsesServerSentEvents;
use LaraGram\Ai\Gateway\Concerns\ResolvesAudioFilenames;
use LaraGram\Ai\Gateway\OpenAiCompatible\Concerns\MapsChatCompletionMessages;
use LaraGram\Ai\Gateway\OpenAiCompatible\Concerns\MapsChatCompletionTools;
use LaraGram\Ai\Gateway\OpenAiCompatible\Concerns\PerformsChatCompletionSteps;
use LaraGram\Ai\Gateway\StepContext;
use LaraGram\Ai\Gateway\TextGenerationOptions;
use LaraGram\Ai\Responses\AudioResponse;
use LaraGram\Ai\Responses\Data\Meta;
use LaraGram\Ai\Responses\Data\TranscriptionSegment;
use LaraGram\Ai\Responses\Data\TranscriptionUsage;
use LaraGram\Ai\Responses\Data\Usage;
use LaraGram\Ai\Responses\EmbeddingsResponse;
use LaraGram\Ai\Responses\TranscriptionResponse;
use RuntimeException;

class MistralGateway implements AudioGateway, EmbeddingGateway, StepTextGateway, TranscriptionGateway
{
    use Concerns\BuildsTextRequests;
    use Concerns\CreatesMistralClient;
    use Concerns\HandlesTextStreaming;
    use Concerns\MapsAttachments;
    use Concerns\ParsesTextResponses;
    use HandlesFailoverErrors;
    use MapsChatCompletionMessages;
    use MapsChatCompletionTools;
    use ParsesServerSentEvents;
    use PerformsChatCompletionSteps;
    use ResolvesAudioFilenames;

    public function __construct(protected Dispatcher $events)
    {
        //
    }

    /**
     * Build the request body for the current text generation step.
     */
    protected function buildStepBody(
        TextProvider $provider,
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
     * {@inheritdoc}
     */
    public function generateAudio(
        AudioProvider $provider,
        string $model,
        string $text,
        string $voice,
        ?string $instructions = null,
        int $timeout = 30,
        array $providerOptions = [],
    ): AudioResponse {
        $voice = match ($voice) {
            'default-male' => 'en_paul_neutral',
            'default-female' => 'gb_jane_neutral',
            default => $voice,
        };

        $response = $this->withErrorHandling(
            $provider->name(),
            fn () => $this->client($provider, $timeout)->post('audio/speech', array_merge($providerOptions, [
                'model' => $model,
                'input' => $text,
                'voice_id' => $voice,
                'response_format' => 'mp3',
            ])),
        );

        $encodedAudio = $response->json('audio_data');

        if (! is_string($encodedAudio) || $encodedAudio === '') {
            throw new RuntimeException('No audio data received from Mistral API.');
        }

        return new AudioResponse(
            $encodedAudio,
            new Usage,
            new Meta($provider->name(), $model),
            'audio/mpeg',
        );
    }

    /**
     * {@inheritdoc}
     */
    public function generateEmbeddings(
        EmbeddingProvider $provider,
        string $model,
        array $inputs,
        int $dimensions,
        int $timeout = 30,
        array $providerOptions = [],
    ): EmbeddingsResponse {
        $response = $this->withErrorHandling(
            $provider->name(),
            fn () => $this->client($provider, $timeout)->post('embeddings', array_merge($providerOptions, [
                'model' => $model,
                'input' => $inputs,
            ])),
        );

        $data = $response->json();

        return new EmbeddingsResponse(
            collect($data['data'] ?? [])->pluck('embedding')->all(),
            new Usage($data['usage']['prompt_tokens'] ?? 0),
            new Meta($provider->name(), $model),
        );
    }

    /**
     * {@inheritdoc}
     */
    public function generateTranscription(
        TranscriptionProvider $provider,
        string $model,
        TranscribableAudio $audio,
        ?string $language = null,
        bool $diarize = false,
        int $timeout = 30,
        array $providerOptions = [],
    ): TranscriptionResponse {
        $params = ['model' => $model];

        if ($diarize) {
            $params['diarize'] = true;
            $params['timestamp_granularities'] = ['segment'];
        } elseif ($language) {
            $params['language'] = $language;
        }

        $response = $this->withErrorHandling(
            $provider->name(),
            fn () => $this->client($provider, $timeout)
                ->attach('file', $audio->content(), $this->audioFilename($audio), array_filter(['Content-Type' => $audio->mimeType()]))
                ->post('audio/transcriptions', $this->multipartParams(array_merge($providerOptions, $params))),
        );

        $data = $response->json();

        return new TranscriptionResponse(
            $data['text'] ?? '',
            collect($data['segments'] ?? [])->map(fn (array $segment): TranscriptionSegment => new TranscriptionSegment(
                $segment['text'] ?? '',
                $segment['speaker_id'] ?? '',
                $segment['start'] ?? 0,
                $segment['end'] ?? 0,
            )),
            new TranscriptionUsage(
                inputTokens: $data['usage']['prompt_tokens'] ?? 0,
                outputTokens: $data['usage']['completion_tokens'] ?? 0,
                audioSeconds: $data['usage']['prompt_audio_seconds'] ?? null,
            ),
            new Meta($provider->name(), $model),
        );
    }

    /**
     * Convert request parameters into multipart parts, expanding array values.
     *
     * @param  array<string, mixed>  $params
     * @return array<int, array{name: string, contents: scalar}>
     */
    protected function multipartParams(array $params): array
    {
        $parts = [];

        foreach ($params as $name => $value) {
            foreach (is_array($value) ? array_values($value) : [$value] as $item) {
                $parts[] = ['name' => $name, 'contents' => $item];
            }
        }

        return $parts;
    }
}
