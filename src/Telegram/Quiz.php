<?php

namespace LaraGram\Ai\Telegram;

use LaraGram\Contracts\JsonSchema\JsonSchema;

class Quiz extends Output
{
    /**
     * Create a new quiz output.
     */
    public function __construct(
        protected int $questions = 1,
        protected int $options = 4,
        protected bool $explanation = true,
        protected bool $anonymous = true,
        protected bool $multipleCorrect = false,
        protected ?int $openPeriod = null,
        protected bool $shuffle = false,
    ) {}

    /**
     * Create a quiz with the given number of questions.
     */
    public static function questions(int $count = 1): static
    {
        return new static(questions: $count);
    }

    /**
     * Set the number of answer options of each question.
     *
     * @return $this
     */
    public function options(int $count): static
    {
        $this->options = max(2, min(12, $count));

        return $this;
    }

    /**
     * Explain the correct answer of each question.
     *
     * @return $this
     */
    public function withExplanation(bool $explanation = true): static
    {
        $this->explanation = $explanation;

        return $this;
    }

    /**
     * Ask the questions without explaining their answers.
     *
     * @return $this
     */
    public function withoutExplanation(): static
    {
        return $this->withExplanation(false);
    }

    /**
     * Show who answered what.
     *
     * @return $this
     */
    public function anonymous(bool $anonymous = true): static
    {
        $this->anonymous = $anonymous;

        return $this;
    }

    /**
     * Allow questions with more than one correct answer.
     *
     * @return $this
     */
    public function multipleCorrect(bool $multiple = true): static
    {
        $this->multipleCorrect = $multiple;

        return $this;
    }

    /**
     * Close each question after the given number of seconds (5-600).
     *
     * @return $this
     */
    public function openFor(int $seconds): static
    {
        $this->openPeriod = max(5, min(600, $seconds));

        return $this;
    }

    /**
     * Show the options of each question in a random order.
     *
     * @return $this
     */
    public function shuffle(bool $shuffle = true): static
    {
        $this->shuffle = $shuffle;

        return $this;
    }

    /**
     * Get the structured output schema the model fills in.
     */
    public function schema(JsonSchema $schema): array
    {
        $question = [
            'question' => $schema->string()->description('The question, at most 300 characters.')->required(),
            'options' => $schema->array()
                ->items($schema->string()->description('An answer option, at most 100 characters.'))
                ->min($this->options)->max($this->options)
                ->description("Exactly {$this->options} answer options.")
                ->required(),
            'correct' => $schema->array()
                ->items($schema->integer()->min(0)->max($this->options - 1))
                ->min(1)->max($this->multipleCorrect ? $this->options - 1 : 1)
                ->description($this->multipleCorrect
                    ? 'The 0-based indexes of the correct options.'
                    : 'The 0-based index of the single correct option.')
                ->required(),
        ];

        if ($this->explanation) {
            $question['explanation'] = $schema->string()
                ->description('Why the answer is correct, at most 200 characters.')
                ->required();
        }

        return [
            'questions' => $schema->array()
                ->items($schema->object(fn () => $question))
                ->min($this->questions)->max($this->questions)
                ->description("Exactly {$this->questions} quiz questions.")
                ->required(),
        ];
    }

    /**
     * Send each question as a quiz poll.
     */
    public function send(array $data, Chat $chat): void
    {
        $sender = Sender::to($chat);

        foreach (array_values($data['questions'] ?? []) as $index => $question) {
            $options = array_slice(array_values(array_filter(
                array_map(fn ($option) => $this->limit($option, 100), (array) ($question['options'] ?? [])),
                fn ($option) => $option !== ''
            )), 0, 12);

            if (count($options) < 2) {
                continue;
            }

            $correct = array_values(array_unique(array_filter(
                array_map('intval', (array) ($question['correct'] ?? [0])),
                fn ($id) => $id >= 0 && $id < count($options)
            )));

            sort($correct);

            $sender->call('sendPoll', array_filter([
                ...$chat->parameters($index === 0),
                'question' => $this->limit($question['question'] ?? '', 300),
                'options' => array_map(fn ($option) => ['text' => $option], $options),
                'type' => 'quiz',
                'is_anonymous' => $this->anonymous,
                'allows_multiple_answers' => count($correct) > 1 ?: null,
                'correct_option_ids' => $correct ?: [0],
                'explanation' => isset($question['explanation']) ? $this->limit($question['explanation'], 200) : null,
                'open_period' => $this->openPeriod,
                'shuffle_options' => $this->shuffle ?: null,
                'reply_markup' => $this->replyMarkup,
            ], fn ($value) => $value !== null));
        }
    }
}
