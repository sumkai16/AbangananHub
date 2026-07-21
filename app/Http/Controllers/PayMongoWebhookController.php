<?php

namespace App\Http\Controllers;

use App\Events\PaymentStatusUpdated;
use App\Models\Notification;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PayMongoWebhookController extends Controller
{
    public function handle(Request $request)
    {
        $signature = $request->header('Paymongo-Signature');

        if (!$signature || !$this->verifySignature($request->getContent(), $signature)) {
            Log::warning('PayMongo webhook: invalid signature');
            return response()->json(['message' => 'Invalid signature'], 403);
        }

        $event = $request->input('data.attributes.type');
        $resource = $request->input('data.attributes.data');

        if ($event === 'checkout_session.payment.paid') {
            $this->handleCheckoutPaid($resource);
        }

        return response()->json(['message' => 'Webhook received'], 200);
    }

protected function handleCheckoutPaid(array $resource): void
{
    $checkoutSessionId = $resource['id'] ?? null;

    if (!$checkoutSessionId) {
        Log::warning('PayMongo webhook: missing checkout session ID');
        return;
    }

    $payment = Payment::where('paymongo_checkout_session_id', $checkoutSessionId)->first();

    if (!$payment) {
        Log::warning('PayMongo webhook: no matching payment', ['session_id' => $checkoutSessionId]);
        return;
    }

    // Idempotency — already processed
    if ($payment->status !== 'Pending') {
        return;
    }

    $paymentIntentId = $resource['attributes']['payment_intent']['id'] ?? null;
    $paymongoPaymentId = $resource['attributes']['payments'][0]['id'] ?? null;

    $payment->update([
        'status' => 'Held',
        'paymongo_payment_intent_id' => $paymentIntentId,
        'paymongo_payment_id' => $paymongoPaymentId,
        'paid_at' => now(),
    ]);

    $reservation = $payment->reservation;
    if ($reservation) {
        $reservation->postSystemMessage($reservation->tenant->name . ' completed the initial payment. Funds are held by AbangananHub.');
    }

    // The tenant is sitting on the agreement page's "Payment Processing"
    // spinner waiting for exactly this.
    PaymentStatusUpdated::dispatch($payment->fresh());

    if ($reservation) {
        $unitLabel = $reservation->unit?->unit_label ?? $reservation->property?->title;
        $amount = '₱' . number_format((float) $payment->amount, 2);

        Notification::notify(
            $reservation->tenant_id,
            'payment',
            'Payment received',
            "Your {$amount} payment for {$unitLabel} is held by AbangananHub until you confirm move-in.",
            route('agreements.show', $reservation),
            $reservation->conversation_id,
        );

        Notification::notify(
            $reservation->property?->landlord_id,
            'payment',
            'Tenant completed payment',
            "{$amount} for {$unitLabel} is held by AbangananHub until the tenant confirms move-in.",
            route('conversations.index', ['active' => $reservation->conversation_id]),
            $reservation->conversation_id,
        );
    }
}

    protected function verifySignature(string $payload, string $signatureHeader): bool
    {
        $secret = config('services.paymongo.webhook_secret');

        if (empty($secret)) {
            Log::warning('PayMongo webhook: PAYMONGO_WEBHOOK_SECRET is not set');
            return false;
        }

        // PayMongo signature format: t=<timestamp>,te=<test_signature>,li=<live_signature>
        $parts = collect(explode(',', $signatureHeader))
            ->mapWithKeys(function ($part) {
                [$key, $value] = explode('=', $part, 2);
                return [$key => $value];
            });

        $timestamp = $parts->get('t');
        $testSignature = $parts->get('te');

        if (!$timestamp || !$testSignature) {
            return false;
        }

        $expectedSignature = hash_hmac('sha256', $timestamp . '.' . $payload, $secret);

        return hash_equals($expectedSignature, $testSignature);
    }
}