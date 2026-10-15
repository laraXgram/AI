<?php

namespace LaraGram\Ai\Telegram;

use LaraGram\Contracts\JsonSchema\JsonSchema;

class Poll extends Output
{
    /**
     * Create a new poll output.
     */
    public function __construct(
        protected int $minOptions = 2,
        protected int $maxOptions = 6,
        protected bool $multipleAnswers = false,
        protected bool $anonymous = true,
        protected ?int $openPeriod = null,
    ) {}

    /**
     * Set how many options the model may write.
     *
     * @return $this
     */
    public function options(int $min, ?int $max = null): static
    {
        $this->minOptions = max(2, min(12, $min));
        $this->maxOptions = max($this->minOptions, min(12, $max ?? $min));

        return $this;
    }

    /**
     * Allow voters to choose more than one option.
     *
     * @return $this
     */
    public function multipleAnswers(bool $multiple = true): static
    {
        $this->multipleAnswers = $multiple;

        return $this;
    }

    /**
     * Show who voted for what.
     *
     * @return $this
     */
    public function anonymous(bool $anonymous = true): static
    {
        $this->anonymous = $anonymous;

        return $this;
    }

    /**
     * Close the poll after the given number of seconds (5-600).
     *
     * @return $this
     */
    public function openFor(int $seconds): static
    {
        $this->openPeriod = max(5, min(600, $seconds));

        return $this;
    }

    /**
     * Get the structured output schema the model fills in.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'question' => $schema->string()->description('The poll question, at most 300 characters.')->required(),
            'options' => $schema->array()
                ->items($schema->string()->description('An answer option, at most 100 characters.'))
                ->min($this->minOptions)->max($this->maxOptions)
                ->description("Between {$this->minOptions} and {$this->maxOptions} answer options.")
                ->required(),
        ];
    }

    /**
     * Send the poll.
     */
    public function send(array $data, Chat $chat): void
    {
        $options = array_slice(array_values(array_filter(
            array_map(fn ($option) => $this->limit($option, 100), (array) ($data['options'] ?? [])),
            fn ($option) => $option !== ''
        )), 0, 12);

        Sender::to($chat)->call('sendPoll', array_filter([
            ...$chat->parameters(),
            'question' => $this->limit($data['question'] ?? '', 300),
            'options' => array_map(fn ($option) => ['text' => $option], $options),
            'type' => 'regular',
            'is_anonymous' => $this->anonymous,
            'allows_multiple_answers' => $this->multipleAnswers ?: null,
            'open_period' => $this->openPeriod,
            'reply_markup' => $this->replyMarkup,
        ], fn ($value) => $value !== null));
    }
}
