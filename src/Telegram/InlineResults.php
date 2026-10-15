<?php

namespace LaraGram\Ai\Telegram;

use LaraGram\Contracts\JsonSchema\JsonSchema;

class InlineResults extends Output
{
    /**
     * Create a new inline results output.
     */
    public function __construct(
        protected int $maxResults = 5,
        protected int $cacheTime = 0,
        protected bool $personal = true,
    ) {}

    /**
     * Set the largest number of results.
     *
     * @return $this
     */
    public function max(int $results): static
    {
        $this->maxResults = max(1, min(50, $results));

        return $this;
    }

    /**
     * Let Telegram cache the results for the given number of seconds.
     *
     * @return $this
     */
    public function cacheFor(int $seconds, bool $personal = true): static
    {
        $this->cacheTime = max(0, $seconds);
        $this->personal = $personal;

        return $this;
    }

    /**
     * Get the structured output schema the model fills in.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'results' => $schema->array()
                ->items($schema->object(fn () => [
                    'title' => $schema->string()->description('The title shown in the results list.')->required(),
                    'description' => $schema->string()->description('A one-line summary.')->required(),
                    'text' => $schema->string()->description('The message sent when the result is chosen, in Markdown.')->required(),
                ]))
                ->min(1)->max($this->maxResults)
                ->required(),
        ];
    }

    /**
     * Answer the inline query with the results, or send the first one to a chat.
     */
    public function send(array $data, Chat $chat): void
    {
        $results = array_values((array) ($data['results'] ?? []));

        if ($chat->inlineQueryId === null) {
            Sender::to($chat)->markdown((string) ($results[0]['text'] ?? ''), $this->replyMarkup);

            return;
        }

        InlineAnswer::articles($chat, array_slice($results, 0, $this->maxResults), $this->cacheTime, $this->personal);
    }
}
