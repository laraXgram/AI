<?php

namespace LaraGram\Ai\Telegram;

use LaraGram\Ai\Contracts\TelegramOutput;
use LaraGram\Support\Str;
use LaraGram\Support\Traits\Conditionable;

abstract class Output implements TelegramOutput
{
    use Conditionable;

    /**
     * The inline keyboard sent with the output, if any.
     *
     * @var array<string, mixed>|null
     */
    protected ?array $replyMarkup = null;

    /**
     * Create a new output instance.
     */
    public static function make(mixed ...$arguments): static
    {
        return new static(...$arguments);
    }

    /**
     * Attach a keyboard to the output, such as one built with the Keyboard builder.
     *
     * @param  array<string, mixed>|object  $markup
     * @return $this
     */
    public function withReplyMarkup(array|object $markup): static
    {
        $this->replyMarkup = is_array($markup) ? $markup : json_decode(json_encode($markup), true);

        return $this;
    }

    /**
     * Trim a value to the given length Telegram accepts.
     */
    protected function limit(mixed $value, int $length): string
    {
        return Str::limit(trim((string) $value), $length - 1, '…');
    }
}
