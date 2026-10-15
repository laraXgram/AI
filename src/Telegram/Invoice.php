<?php

namespace LaraGram\Ai\Telegram;

use LaraGram\Contracts\JsonSchema\JsonSchema;
use LaraGram\Support\Str;

class Invoice extends Output
{
    /**
     * Create a new invoice output.
     *
     * The price is always set by the application; the model only writes the
     * title and description of the invoice.
     *
     * @param  int  $amount  The price in the smallest units of the currency (Stars for XTR).
     */
    public function __construct(
        protected int $amount,
        protected string $currency = 'XTR',
        protected ?string $providerToken = null,
        protected ?string $payload = null,
        protected string $label = 'Price',
    ) {}

    /**
     * Create an invoice paid with the given number of Telegram Stars.
     */
    public static function stars(int $amount): static
    {
        return new static($amount);
    }

    /**
     * Create an invoice paid in a currency through a payment provider.
     *
     * @param  int  $amount  The price in the smallest units of the currency, such as cents.
     */
    public static function price(int $amount, string $currency, string $providerToken): static
    {
        return new static($amount, strtoupper($currency), $providerToken);
    }

    /**
     * Set the payload the bot receives with the payment.
     *
     * @return $this
     */
    public function payload(string $payload): static
    {
        $this->payload = $payload;

        return $this;
    }

    /**
     * Set the label of the price.
     *
     * @return $this
     */
    public function label(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    /**
     * Get the structured output schema the model fills in.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'title' => $schema->string()->description('The product name, at most 32 characters.')->required(),
            'description' => $schema->string()->description('The product description, at most 255 characters.')->required(),
        ];
    }

    /**
     * Send the invoice.
     */
    public function send(array $data, Chat $chat): void
    {
        Sender::to($chat)->call('sendInvoice', array_filter([
            ...$chat->parameters(),
            'title' => $this->limit($data['title'] ?? '', 32),
            'description' => $this->limit($data['description'] ?? '', 255),
            'payload' => $this->payload ?? 'ai-invoice:'.Str::random(16),
            'currency' => $this->currency,
            'prices' => [['label' => $this->label, 'amount' => $this->amount]],
            'provider_token' => $this->currency === 'XTR' ? null : $this->providerToken,
            'reply_markup' => $this->replyMarkup,
        ], fn ($value) => $value !== null));
    }
}
