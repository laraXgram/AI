<?php

namespace LaraGram\Ai\Telegram;

use RuntimeException;

class TelegramException extends RuntimeException
{
    /**
     * Create an exception for a failed Telegram call.
     */
    public static function failed(string $method, ?string $description, ?int $code = null): static
    {
        return new static("Telegram rejected [{$method}]: ".($description ?? 'unknown error'), $code ?? 0);
    }
}
