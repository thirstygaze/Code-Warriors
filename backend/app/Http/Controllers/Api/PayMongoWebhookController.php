<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CanteenNotification;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use JsonException;

class PayMongoWebhookController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $webhookSecret = config('services.paymongo.webhook_secret');

        if (! is_string($webhookSecret) || $webhookSecret === '') {
            Log::error('PayMongo webhook rejected because PAYMONGO_WEBHOOK_SECRET is not configured.');

            return response()->json(['message' => 'Payment webhook is not configured.'], 503);
        }

        $rawBody = $request->getContent();

        try {
            $payload = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return response()->json(['message' => 'Invalid webhook payload.'], 400);
        }

        if (! is_array($payload) || ! $this->hasValidSignature($request, $payload, $rawBody, $webhookSecret)) {
            Log::warning('PayMongo webhook rejected because its signature was invalid.');

            return response()->json(['message' => 'Invalid webhook signature.'], 401);
        }

        $event = $payload['data'] ?? [];

        if (($event['type'] ?? null) !== 'checkout_session.payment.paid') {
            return response()->json(['received' => true]);
        }

        $session = $event['data'] ?? [];
        $attributes = $session['attributes'] ?? [];
        $sessionId = $session['id'] ?? null;
        $referenceNumber = $attributes['reference_number'] ?? null;

        if ((! is_string($sessionId) || $sessionId === '') && (! is_string($referenceNumber) || $referenceNumber === '')) {
            Log::warning('PayMongo paid event did not contain a checkout session ID or reference number.');

            return response()->json(['received' => true]);
        }

        $paymentQuery = Payment::query()->where('payment_provider', 'paymongo');
        $payment = is_string($sessionId) && $sessionId !== ''
            ? (clone $paymentQuery)->where('provider_checkout_id', $sessionId)->first()
            : null;
        $payment ??= is_string($referenceNumber) && $referenceNumber !== ''
            ? (clone $paymentQuery)->where('reference_number', $referenceNumber)->first()
            : null;

        if (! $payment) {
            Log::warning('PayMongo payment webhook references an unknown checkout session.', [
                'checkout_session_id' => $sessionId,
                'reference_number' => $referenceNumber,
            ]);

            return response()->json(['received' => true]);
        }

        if (
            (is_string($payment->provider_checkout_id)
                && is_string($sessionId)
                && $payment->provider_checkout_id !== $sessionId)
            || (is_string($payment->reference_number)
                && is_string($referenceNumber)
                && $payment->reference_number !== $referenceNumber)
        ) {
            Log::warning('PayMongo paid event session and reference details do not match the stored payment.', [
                'payment_id' => $payment->payment_id,
                'checkout_session_id' => $sessionId,
                'reference_number' => $referenceNumber,
            ]);

            return response()->json(['received' => true]);
        }

        DB::transaction(function () use ($payment, $attributes): void {
            $lockedPayment = Payment::query()->lockForUpdate()->findOrFail($payment->payment_id);

            if ($lockedPayment->payment_status === 'paid') {
                return;
            }

            $checkoutSessionId = $attributes['payment_intent']['id'] ?? null;
            $firstPayment = $attributes['payments'][0] ?? [];
            $paymentMethod = $firstPayment['attributes']['source']['type'] ?? null;

            $lockedPayment->update([
                'payment_status' => 'paid',
                'paid_at' => now(),
                'provider_payment_intent_id' => is_string($checkoutSessionId) ? $checkoutSessionId : $lockedPayment->provider_payment_intent_id,
                'payment_method' => is_string($paymentMethod) ? $paymentMethod : $lockedPayment->payment_method,
            ]);

            User::query()
                ->whereIn('role', ['staff', 'admin'])
                ->where('is_active', true)
                ->pluck('id')
                ->each(fn (int $staffId) => CanteenNotification::create([
                    'user_id' => $staffId,
                    'order_id' => $lockedPayment->order_id,
                    'message' => "New paid order #{$lockedPayment->order_id} is ready to prepare.",
                ]));
        });

        return response()->json(['received' => true]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function hasValidSignature(Request $request, array $payload, string $rawBody, string $secret): bool
    {
        $parts = [];
        foreach (explode(',', (string) $request->header('Paymongo-Signature')) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, null);
            if (is_string($value)) {
                $parts[$key] = $value;
            }
        }

        $timestamp = $parts['t'] ?? null;
        if (! is_string($timestamp) || ! ctype_digit($timestamp) || abs(now()->timestamp - (int) $timestamp) > 300) {
            return false;
        }

        $eventData = $payload['data'] ?? [];
        $signatureKey = ($eventData['livemode'] ?? false) ? 'li' : 'te';
        $providedSignature = $parts[$signatureKey] ?? null;
        if (! is_string($providedSignature)) {
            return false;
        }

        $expectedSignature = hash_hmac('sha256', $timestamp.'.'.$rawBody, $secret);

        return hash_equals($expectedSignature, $providedSignature);
    }
}
