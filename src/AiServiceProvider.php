<?php

namespace LaraGram\Ai;

use Closure;
use LaraGram\Queue\Events\Looping;
use LaraGram\Support\Collection;
use LaraGram\Support\Facades\Event;
use LaraGram\Support\ServiceProvider;
use LaraGram\Support\Str;
use LaraGram\Support\Stringable;
use LaraGram\Ai\Agents\SummarizeAgent;
use LaraGram\Ai\Classification\Boolean;
use LaraGram\Ai\Classification\CollectionChoice;
use LaraGram\Ai\Console\Commands\ChatCommand;
use LaraGram\Ai\Console\Commands\MakeAgentCommand;
use LaraGram\Ai\Console\Commands\MakeAgentMiddlewareCommand;
use LaraGram\Ai\Console\Commands\MakeToolCommand;
use LaraGram\Ai\Contracts\ConversationStore;
use LaraGram\Ai\Enums\Lab;
use LaraGram\Ai\Responses\AudioResponse;
use LaraGram\Ai\Responses\Data\BooleanAnswer;
use LaraGram\Ai\Storage\DatabaseConversationStore;

class AiServiceProvider extends ServiceProvider
{
    /**
     * Register the package's services.
     */
    #[\Override]
    public function register(): void
    {
        $this->app->singleton(AiManager::class, fn ($app): AiManager => new AiManager($app));

        $this->app->singleton(ConversationStore::class, fn (): DatabaseConversationStore => new DatabaseConversationStore(
            config('ai.conversations.connection'),
        ));

        $this->mergeConfigFrom(__DIR__.'/../config/ai.php', 'ai');
    }

    /**
     * Bootstrap the package's services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->registerCommands();
            $this->registerPublishing();
        }

        $this->registerStateFlushing();

        // Embeddings macro...
        Stringable::macro('toEmbeddings', function (
            Lab|array|string|null $provider = null,
            ?int $dimensions = null,
            ?string $model = null,
            bool|int|null $cache = null,
            ?int $timeout = null,
            array|Closure $providerOptions = [],
        ) {
            $request = Embeddings::for([$this->value()]);

            if ($dimensions) {
                $request->dimensions($dimensions);
            }

            if ($cache === false) {
                $request->cache(0);
            } elseif (! is_null($cache)) {
                $request->cache(is_int($cache) ? $cache : null);
            }

            if (! is_null($timeout)) {
                $request->timeout($timeout);
            }

            if (filled($providerOptions)) {
                $request->withProviderOptions($providerOptions);
            }

            return $request->generate(provider: $provider, model: $model)->embeddings[0];
        });

        // Audio macro...
        Stringable::macro('toAudio', function (
            Lab|array|string|null $provider = null,
            ?string $voice = null,
            ?string $instructions = null,
            ?string $model = null,
            ?int $timeout = null,
        ): AudioResponse {
            $request = Audio::of($this->value());

            if (! is_null($voice)) {
                $request->voice($voice);
            }

            if (! is_null($instructions)) {
                $request->instructions($instructions);
            }

            if (! is_null($timeout)) {
                $request->timeout($timeout);
            }

            return $request->generate(provider: $provider, model: $model);
        });

        // Summarize macros...
        Stringable::macro('summarize', fn (
            int $sentences = 3,
            Lab|array|string|null $provider = null,
            ?string $model = null,
            ?int $timeout = null,
        ): string => (new SummarizeAgent($sentences))
            ->prompt($this->value(), provider: $provider, model: $model, timeout: $timeout)->text);

        Str::macro('summarize', fn (
            string $value,
            int $sentences = 3,
            Lab|array|string|null $provider = null,
            ?string $model = null,
            ?int $timeout = null,
        ): string => (new SummarizeAgent($sentences))
            ->prompt($value, provider: $provider, model: $model, timeout: $timeout)->text);

        // Decision macros...
        $decide = function (
            string $value,
            string $question,
            array $criteria,
            float $threshold,
            Lab|array|string|null $provider,
            ?string $model,
            ?int $timeout,
        ): bool {
            $request = Classification::of($value)->question('decision', new Boolean($question, $criteria ?: null));

            if (! is_null($timeout)) {
                $request->timeout($timeout);
            }

            $answer = $request->classify($provider, $model)->answer('decision');

            assert($answer instanceof BooleanAnswer);

            return $answer->isTrue($threshold);
        };

        Stringable::macro('decide', fn (
            string $question,
            array $criteria = [],
            float $threshold = 0.5,
            Lab|array|string|null $provider = null,
            ?string $model = null,
            ?int $timeout = null,
        ): bool => $decide($this->value(), $question, $criteria, $threshold, $provider, $model, $timeout));

        Str::macro('decide', fn (
            string $value,
            string $question,
            array $criteria = [],
            float $threshold = 0.5,
            Lab|array|string|null $provider = null,
            ?string $model = null,
            ?int $timeout = null,
        ): bool => $decide($value, $question, $criteria, $threshold, $provider, $model, $timeout));

        Collection::macro('decide', fn (
            string $question,
            string|array $text,
            Closure|string|null $by = null,
            Closure|array|string|null $describe = null,
            ?float $threshold = null,
            Lab|array|string|null $provider = null,
            ?string $model = null,
            ?int $timeout = null,
        ): mixed => (new CollectionChoice($this, $by, $describe))
            ->decide($question, $text, $threshold, $provider, $model, $timeout));

        // Reranking macro...
        Collection::macro('rerank', function (
            Closure|array|string $by,
            string $query,
            ?int $limit = null,
            Lab|array|string|null $provider = null,
            ?string $model = null,
            int $timeout = 30,
        ) {
            $resolver = match (true) {
                $by instanceof Closure => $by,
                is_array($by) => fn ($item): string|false => json_encode(
                    (new Collection($by))->mapWithKeys(fn ($field): array => [$field => data_get($item, $field)])->all()
                ),
                default => fn ($item) => data_get($item, $by),
            };

            $response = Reranking::of($this->map($resolver)->values()->all())
                ->limit($limit)
                ->timeout($timeout)
                ->rerank($query, $provider, $model);

            return (new Collection($response->results))->map(
                fn ($result): mixed => $this->values()[$result->index]
            );
        });
    }

    /**
     * Register the package's console commands.
     */
    protected function registerCommands(): void
    {
        $this->commands([
            // ChatCommand::class,
            MakeAgentCommand::class,
            MakeAgentMiddlewareCommand::class,
            MakeToolCommand::class,
        ]);
    }

    /**
     * Register the package's publishable resources.
     */
    protected function registerPublishing(): void
    {
        $this->publishes([
            __DIR__.'/../config/ai.php' => config_path('ai.php'),
        ], ['ai', 'ai-config']);

        $this->publishes([
            __DIR__.'/../stubs/agent.stub' => base_path('stubs/agent.stub'),
            __DIR__.'/../stubs/structured-agent.stub' => base_path('stubs/structured-agent.stub'),
            __DIR__.'/../stubs/tool.stub' => base_path('stubs/tool.stub'),
            __DIR__.'/../stubs/agent-middleware.stub' => base_path('stubs/agent-middleware.stub'),
        ], 'ai-stubs');

        $this->publishesMigrations([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ]);
    }

    /**
     * Flush on-demand providers between queue jobs and Surge operations.
     */
    protected function registerStateFlushing(): void
    {
        $flush = function (): void {
            if (app()->resolved(AiManager::class)) {
                app(AiManager::class)->flushState();
            }
        };

        Event::listen(Looping::class, $flush);
        Event::listen('LaraGram\Surge\Contracts\OperationTerminated', $flush);
    }
}
