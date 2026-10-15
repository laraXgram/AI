<?php

namespace LaraGram\Ai\Telegram;

use LaraGram\Contracts\JsonSchema\JsonSchema;

class Location extends Output
{
    /**
     * Create a new location output.
     */
    public function __construct(protected bool $venue = false) {}

    /**
     * Create a venue: a location with a title and an address.
     */
    public static function venue(): static
    {
        return new static(venue: true);
    }

    /**
     * Get the structured output schema the model fills in.
     */
    public function schema(JsonSchema $schema): array
    {
        $fields = [
            'latitude' => $schema->number()->description('Latitude in degrees.')->required(),
            'longitude' => $schema->number()->description('Longitude in degrees.')->required(),
        ];

        if ($this->venue) {
            $fields['title'] = $schema->string()->description('The name of the place.')->required();
            $fields['address'] = $schema->string()->description('The address of the place.')->required();
        }

        return $fields;
    }

    /**
     * Send the location or venue.
     */
    public function send(array $data, Chat $chat): void
    {
        $point = [
            'latitude' => max(-90, min(90, (float) ($data['latitude'] ?? 0))),
            'longitude' => max(-180, min(180, (float) ($data['longitude'] ?? 0))),
        ];

        if ($this->venue) {
            Sender::to($chat)->call('sendVenue', [
                ...$chat->parameters(),
                ...$point,
                'title' => $this->limit($data['title'] ?? '', 256),
                'address' => $this->limit($data['address'] ?? '', 256),
                'reply_markup' => $this->replyMarkup,
            ]);

            return;
        }

        Sender::to($chat)->call('sendLocation', [...$chat->parameters(), ...$point, 'reply_markup' => $this->replyMarkup]);
    }
}
