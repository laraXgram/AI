<?php

namespace LaraGram\Ai\Files;

use LaraGram\Contracts\Support\Arrayable;
use InvalidArgumentException;
use JsonSerializable;
use LaraGram\Ai\Contracts\Files\HasProviderId;
use LaraGram\Ai\Files\Concerns\CanBeRetrievedOrDeletedFromProvider;

class ProviderDocument extends Document implements Arrayable, HasProviderId, JsonSerializable
{
    use CanBeRetrievedOrDeletedFromProvider;

    public function __construct(public string $id) {}

    /**
     * Get the provider ID for the stored file.
     */
    public function id(): string
    {
        return $this->id;
    }

    /**
     * @throws InvalidArgumentException
     */
    public function content(): string
    {
        throw new InvalidArgumentException(
            'ProviderDocument cannot be read directly. It is a reference to a file stored on an external provider.'
        );
    }

    /**
     * Get the instance as an array.
     */
    public function toArray(): array
    {
        return [
            'type' => 'provider-document',
            'id' => $this->id,
            'name' => $this->name(),
        ];
    }

    /**
     * Get the JSON serializable representation of the instance.
     */
    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }
}
