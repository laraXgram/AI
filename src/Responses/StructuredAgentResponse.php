<?php

namespace LaraGram\Ai\Responses;

use ArrayAccess;
use LaraGram\Contracts\Support\Arrayable;
use LaraGram\Contracts\Support\Jsonable;
use LaraGram\Support\Collection;
use JsonSerializable;
use LaraGram\Ai\Contracts\TelegramOutput;
use LaraGram\Ai\Telegram\Chat;
use LaraGram\Ai\Telegram\Sender;
use LaraGram\Ai\Responses\Data\Meta;
use LaraGram\Ai\Responses\Data\TextUsage;

class StructuredAgentResponse extends AgentResponse implements Arrayable, ArrayAccess, Jsonable, JsonSerializable
{
    use ProvidesStructuredResponse;

    /**
     * The Telegram output the structured data describes, if any.
     */
    public ?TelegramOutput $telegramOutput = null;

    public function __construct(string $invocationId, array $structured, string $text, TextUsage $usage, Meta $meta)
    {
        parent::__construct($invocationId, $text, $usage, $meta);

        $this->structured = $structured;
        $this->toolCalls = new Collection;
        $this->toolResults = new Collection;
    }

    /**
     * Set the Telegram output the structured data describes.
     */
    public function withTelegramOutput(?TelegramOutput $output): static
    {
        $this->telegramOutput = $output;

        return $this;
    }

    /**
     * Get the instance as an array.
     */
    public function toArray(): array
    {
        return $this->structured;
    }

    /**
     * Convert the object to its JSON representation.
     *
     * @param  int  $options
     */
    public function toJson($options = 0): string
    {
        return json_encode($this->structured, $options);
    }

    /**
     * Get the JSON serializable representation of the instance.
     */
    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }

    /**
     * Get the string representation of the object.
     */
    #[\Override]
    public function __toString(): string
    {
        return (string) json_encode($this->structured);
    }

    /**
     * Deliver the structured output to the given chat.
     */
    protected function deliverTo(Chat $chat): void
    {
        if ($this->hasPendingApprovals() || $this->telegramOutput === null) {
            if (! $this->hasPendingApprovals()) {
                Sender::to($chat)->markdown("```json\n".json_encode($this->structured, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n```");

                return;
            }

            parent::deliverTo($chat);

            return;
        }

        Sender::to($chat)->answerCallback();

        $this->telegramOutput->send($this->structured, $chat);
    }

    /**
     * Get the value an HTTP route would return for this response.
     */
    protected function toHttpValue(): mixed
    {
        return $this->toArray();
    }
}
