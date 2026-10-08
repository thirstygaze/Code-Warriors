<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class PayMongoService
{
    /**
     * @return array{checkout_id: string, checkout_url: string, payment_intent_id: ?string}
     */
    public function createCheckoutSession(Order $order): array
    {
        $secretKey = config('services.paymongo.secret_key');

        if (! is_string($secretKey) || $secretKey === '') {
            throw new RuntimeException('PayMongo is not configured. Set PAYMONGO_SECRET_KEY in the backend environment.');
        }

        $order->loadMissing(['items.menuItem', 'student', 'payment']);

        $lineItems = $order->items->map(fn ($item): array => [
            'name' => $item->menuItem->item_name,
            'amount' => (int) round((float) $item->unit_price * 100),
            'currency' => config('services.paymongo.currency', 'PHP'),
            'quantity' => $item->quantity,
        ])->all();

        try {
            $response = Http::withBasicAuth($secretKey, '')
                ->acceptJson()
                ->asJson()
                ->timeout(15)
                ->post('https://api.paymongo.com/v2/checkout_sessions', [
                    'data' => [
                        'attributes' => [
                            'line_items' => $lineItems,
                            'payment_method_types' => config('services.paymongo.payment_methods'),
                            'success_url' => $this->returnUrl('success', $order),
                            'cancel_url' => $this->returnUrl('cancelled', $order),
                            'reference_number' => $order->payment->reference_number,
                            'description' => "Smartbite order #{$order->order_id}",
                            'send_email_receipt' => true,
                            'metadata' => [
                                'order_id' => (string) $order->order_id,
                            ],
                        ],
                    ],
                ]);
            $response->throw();
        } catch (ConnectionException|RequestException $exception) {
            $status = $exception instanceof RequestException
                ? $exception->response->status()
                : 'connection error';

            throw new RuntimeException("PayMongo Checkout could not be created ({$status}). Please try again.", previous: $exception);
        }

        $session = $response->json('data');
        $sessionId = $session['id'] ?? null;
        $checkoutUrl = $session['attributes']['checkout_url'] ?? null;

        if (! is_string($sessionId) || ! is_string($checkoutUrl)) {
            throw new RuntimeException('PayMongo returned an incomplete Checkout Session response.');
        }

        return [
            'checkout_id' => $sessionId,
            'checkout_url' => $checkoutUrl,
            'payment_intent_id' => $session['attributes']['payment_intent']['id'] ?? null,
        ];
    }

    private function returnUrl(string $result, Order $order): string
    {
        return rtrim(config('smartbite.frontend_url'), '/').'/payment-result?'.http_build_query([
            'result' => $result,
            'order_id' => $order->order_id,
        ]);
    }
}
