<?php

namespace App\Http\Controllers;

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
    $paymentMethod = Payment::resolvePaymongoMethod(
        $resource['attributes']['payment_method_used']
            ?? $resource['attributes']['payments'][0]['attributes']['source']['type'] ?? null
    ) ?? $payment->payment_method;

    // Monthly rent settles straight to the landlord — there is no handover
    // left to protect once a tenant already occupies the unit, unlike the
    // Initial payment which stays escrowed until move-in is confirmed.
    $isRent = $payment->payment_type === 'Monthly';

    $payment->update([
        'status' => $isRent ? 'Paid' : 'Held',
        'payment_method' => $paymentMethod,
        'paymongo_payment_intent_id' => $paymentIntentId,
        'paymongo_payment_id' => $paymongoPaymentId,
        'paid_at' => now(),
        // Rent settles straight to the landlord with no escrow step, so it is
        // payout-eligible the moment it's paid. The initial payment isn't —
        // it only becomes payout-eligible once escrow releases it.
        'payout_status' => $isRent ? 'Pending Payout' : null,
    ]);

    // The broadcast and both notifications are raised by PaymentObserver off
    // the status change above — the tenant sitting on the agreement/ledger
    // page's "Payment Processing" spinner is waiting for exactly that.
    $message = $isRent
        ? $payment->reservation->tenant->name . ' paid rent for ' . ($payment->billing_period?->format('M Y') ?? 'this period') . ' online.'
        : $payment->reservation->tenant->name . ' completed the initial payment. Funds are held by AbangananHub.';

    $payment->reservation?->postSystemMessage($message);
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