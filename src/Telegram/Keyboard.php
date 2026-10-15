<?php

namespace LaraGram\Ai\Telegram;

use LaraGram\Contracts\JsonSchema\JsonSchema;

class Keyboard extends Output
{
    /**
     * The callback data the model may choose from, with what each one means.
     *
     * @var array<string, string>
     */
    protected array $callbacks = [];

    /**
     * Create a new keyboard output.
     *
     * @param  "inline"|"reply"  $type
     */
    public function __construct(
        protected string $type = 'inline',
        protected int $maxButtons = 6,
        protected int $columns = 2,
        protected bool $urls = false,
        protected string $prefix = 'ai:',
    ) {}

    /**
     * Create a message with an inline keyboard.
     */
    public static function inline(): static
    {
        return new static('inline');
    }

    /**
     * Create a message with a reply keyboard of suggested answers.
     */
    public static function reply(): static
    {
        return new static('reply');
    }

    /**
     * Let the model choose buttons from the given callback data, keyed by data with a description of each.
     *
     * Without callbacks, an inline button sends the prefix followed by its label.
     *
     * @param  array<string, string>  $callbacks
     * @return $this
     */
    public function callbacks(array $callbacks): static
    {
        $this->callbacks = $callbacks;

        return $this;
    }

    /**
     * Allow the model to add link buttons.
     *
     * @return $this
     */
    public function urls(bool $urls = true): static
    {
        $this->urls = $urls;

        return $this;
    }

    /**
     * Set the prefix of the callback data of free buttons.
     *
     * @return $this
     */
    public function prefix(string $prefix): static
    {
        $this->prefix = $prefix;

        return $this;
    }

    /**
     * Set the largest number of buttons.
     *
     * @return $this
     */
    public function max(int $buttons): static
    {
        $this->maxButtons = max(1, $buttons);

        return $this;
    }

    /**
     * Set the number of buttons in a row.
     *
     * @return $this
     */
    public function columns(int $columns): static
    {
        $this->columns = max(1, min(8, $columns));

        return $this;
    }

    /**
     * Get the structured output schema the model fills in.
     */
    public function schema(JsonSchema $schema): array
    {
        $button = ['text' => $schema->string()->description('The button label, a few words.')->required()];

        if ($this->type === 'inline' && $this->callbacks !== []) {
            $button['callback'] = $schema->string()->enum(array_keys($this->callbacks))->description(
                'What the button does: '.collect($this->callbacks)->map(fn ($meaning, $data) => "\"{$data}\" = {$meaning}")->implode('; ').'.'
            )->required(! $this->urls);
        }

        if ($this->type === 'inline' && $this->urls) {
            $button['url'] = $schema->string()->description('An https link the button opens, if it is a link button.')->nullable();
        }

        return [
            'text' => $schema->string()->description('The message, in Markdown.')->required(),
            'buttons' => $schema->array()
                ->items($schema->object(fn () => $button))
                ->min(1)->max($this->maxButtons)
                ->description($this->type === 'reply'
                    ? 'Short answers the user may tap instead of typing.'
                    : 'The buttons under the message.')
                ->required(),
        ];
    }

    /**
     * Send the message with its keyboard.
     */
    public function send(array $data, Chat $chat): void
    {
        $buttons = [];

        foreach (array_slice((array) ($data['buttons'] ?? []), 0, $this->maxButtons) as $button) {
            $text = $this->limit($button['text'] ?? '', 64);

            if ($text === '') {
                continue;
            }

            if ($this->type === 'reply') {
                $buttons[] = ['text' => $text];
            } elseif ($this->urls && ! empty($button['url']) && preg_match('/^https?:\/\//i', $button['url'])) {
                $buttons[] = ['text' => $text, 'url' => $button['url']];
            } elseif ($this->callbacks !== []) {
                if (isset($button['callback']) && array_key_exists($button['callback'], $this->callbacks)) {
                    $buttons[] = ['text' => $text, 'callback_data' => (string) $button['callback']];
                }
            } else {
                $buttons[] = ['text' => $text, 'callback_data' => mb_strcut($this->prefix.$text, 0, 64, 'UTF-8')];
            }
        }

        $rows = array_chunk($buttons, $this->columns);

        $markup = $rows === [] ? $this->replyMarkup : match ($this->type) {
            'reply' => ['keyboard' => $rows, 'resize_keyboard' => true, 'one_time_keyboard' => true],
            default => ['inline_keyboard' => $rows],
        };

        Sender::to($chat)->markdown((string) ($data['text'] ?? ''), $markup);
    }
}
