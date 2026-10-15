<?php

namespace LaraGram\Ai\Gateway\Groq;

use LaraGram\Contracts\Events\Dispatcher;
use LaraGram\Ai\Contracts\Files\TranscribableAudio;
use LaraGram\Ai\Contracts\Gateway\StepTextGateway;
use LaraGram\Ai\Contracts\Gateway\TranscriptionGateway;
use LaraGram\Ai\Contracts\Providers\SupportsCodeExecution;
use LaraGram\Ai\Contracts\Providers\SupportsWebSearch;
use LaraGram\Ai\Contracts\Providers\TranscriptionProvider;
use LaraGram\Ai\Gateway\Concerns\HandlesFailoverErrors;
use LaraGram\Ai\Gateway\Concerns\ParsesServerSentEvents;
use LaraGram\Ai\Gateway\Concerns\ResolvesAudioFilenames;
use LaraGram\Ai\Gateway\OpenAiCompatible\Concerns\MapsChatCompletionMessages;
use LaraGram\Ai\Gateway\OpenAiCompatible\Concerns\MapsChatCompletionTools;
use LaraGram\Ai\Gateway\OpenAiCompatible\Concerns\PerformsChatCompletionSteps;
use LaraGram\Ai\Providers\Provider;
use LaraGram\Ai\Providers\Tools\CodeExecution;
use LaraGram\Ai\Providers\Tools\ProviderTool;
use LaraGram\Ai\Providers\Tools\WebSearch;
use LaraGram\Ai\Responses\Data\Meta;
use LaraGram\Ai\Responses\Data\TranscriptionSegment;
use LaraGram\Ai\Responses\Data\TranscriptionUsage;
use LaraGram\Ai\Responses\TranscriptionResponse;
use LogicException;
use RuntimeException;

class GroqGateway implements StepTextGateway, TranscriptionGateway
{
    use Concerns\BuildsTextRequests;
    use Concerns\CreatesGroqClient;
    use Concerns\HandlesTextStreaming;
    use Concerns\MapsAttachments;
    use Concerns\ParsesTextResponses;
    use HandlesFailoverErrors;
    use MapsChatCompletionMessages;
    use MapsChatCompletionTools;
    use ParsesServerSentEvents;
    use PerformsChatCompletionSteps;
    use ResolvesAudioFilenames;

    public function __construct(protected Dispatcher $events) {}

    /**
     * Map a provider tool to a Groq built-in tool definition.
     */
    protected function mapProviderTool(ProviderTool $tool, Provider $provider): array
    {
        return match (true) {
            $tool instanceof CodeExecution => $this->mapCodeExecutionTool($tool, $provider),
            $tool instanceof WebSearch => $this->mapWebSearchTool($tool, $provider),
            default => throw new RuntimeException('Groq does not support ['.class_basename($tool).'] provider tools.'),
        };
    }

    /**
     * Map a code execution tool to a Groq code interpreter definition.
     */
    protected function mapCodeExecutionTool(CodeExecution $tool, Provider $provider): array
    {
        if (! $provider instanceof SupportsCodeExecution) {
            throw new RuntimeException('Provider ['.$provider->name().'] does not support code execution.');
        }

        return [
            'type' => 'code_interpreter',
            ...$provider->codeExecutionToolOptions($tool),
        ];
    }

    /**
     * Map a web search tool to a Groq browser search definition.
     */
    protected function mapWebSearchTool(WebSearch $tool, Provider $provider): array
    {
        if (! $provider instanceof SupportsWebSearch) {
            throw new RuntimeException('Provider ['.$provider->name().'] does not support web search.');
        }

        return [
            'type' => 'browser_search',
            ...$provider->webSearchToolOptions($tool),
        ];
    }

    /**
     * The status codes that indicate Groq is transiently unavailable and the request should fail over.
     *
     * @return list<int>
     */
    protected function overloadedStatusCodes(): array
    {
        // The status codes Groq documents as transient: 498 is "flex tier capacity exceeded", alongside 502 and 503.
        return [498, 502, 503];
    }

    /**
     * Generate text from the given audio.
     *
     * @param  array<string, mixed>  $providerOptions
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
        if ($diarize) {
            throw new LogicException(
                'Groq does not support diarized transcription. Use the OpenAI, ElevenLabs, Mistral, or Gemini provider for diarization.'
            );
        }

        $response = $this->withErrorHandling(
            $provider->name(),
            fn () => $this->client($provider, $timeout)
                ->attach('file', $audio->content(), $this->audioFilename($audio), array_filter(['Content-Type' => $audio->mimeType()]))
                ->post('audio/transcriptions', array_merge($providerOptions, array_filter([
                    'model' => $model,
                    'language' => $language,
                    'response_format' => $providerOptions['response_format'] ?? 'verbose_json',
                ]))),
        );

        $data = $response->json();

        return new TranscriptionResponse(
            $data['text'] ?? '',
            collect($data['segments'] ?? [])->map(fn (array $segment): TranscriptionSegment => new TranscriptionSegment(
                $segment['text'] ?? '',
                $segment['speaker'] ?? '',
                $segment['start'] ?? 0,
                $segment['end'] ?? 0,
            )),
            new TranscriptionUsage(audioSeconds: $data['duration'] ?? null),
            new Meta($provider->name(), $model),
        );
    }
}
