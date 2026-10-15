<?php

namespace LaraGram\Ai\Telegram;

use LaraGram\Ai\Middleware\LimitUsage;
use LaraGram\Foundation\Bot\Events\RequestHandled;
use LaraGram\Request\Request;
use Throwable;

class Payments
{
    /**
     * Complete the purchase of tokens offered by the LimitUsage middleware.
     *
     * Only invoices created by the AI SDK are handled, so the application's own
     * payment listens keep working.
     */
    public static function handle(RequestHandled $event): void
    {
        $request = $event->request;

        if (! $request instanceof Request) {
            return;
        }

        try {
            if ($query = $request->pre_checkout_query) {
                if (str_starts_with((string) $query->invoice_payload, LimitUsage::PAYLOAD_PREFIX)) {
                    Chat::make($query->from->id)->via(Chat::fromRequest($request)?->connection)->messenger()->call('answerPreCheckoutQuery', [
                        'pre_checkout_query_id' => $query->id,
                        'ok' => true,
                    ]);
                }

                return;
            }

            $payment = $request->message?->successful_payment;

            if ($payment === null || ! str_starts_with((string) $payment->invoice_payload, LimitUsage::PAYLOAD_PREFIX)) {
                return;
            }

            [$tokens, $user] = array_pad(explode(':', substr($payment->invoice_payload, strlen(LimitUsage::PAYLOAD_PREFIX))), 2, null);

            LimitUsage::credit($user ?? $request->message->from->id, (int) $tokens);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
