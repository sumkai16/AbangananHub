<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Services\PaymongoCheckout;
use App\Services\PaymongoRefundEvents;
use App\Services\PaymongoTransferEvents;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PayMongoWebhookController extends Controller
{
    public function handle(Request $request)
    {
        $signature = $request->header('Paymongo-Signature');

        // The event says which mode it came from; that picks which of the header's two signatures to check.
        $livemode = $request->boolean('data.attributes.livemode');

        if (!$signature || !$this->verifySignature($request->getContent(), $signature, $livemode)) {
            Log::warning('PayMongo webhook: invalid signature');

            return response()->json(['message' => 'Invalid signature'], 403);
        }

        $event = $request->input('data.attributes.type');
        $resource = $request->input('data.attributes.data');

        if ($event === 'checkout_session.payment.paid') {
            $this->handleCheckoutPaid($resource);
        } else {
            // Refund and transfer events each have their own handler; both ignore events that are not theirs.
            PaymongoRefundEvents::handle((string) $event, (array) $resource)
                || PaymongoTransferEvents::handle((string) $event, (array) $resource);
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

        $sessionPayments = $resource['attributes']['payments'] ?? [];

        // Settle only what we asked for; a different amount stays Pending and the admins are told.
        if (! PaymongoCheckout::paidAmountMatches($sessionPayments, $payment)) {
            return;
        }

        // Every row the checkout funded (rent, deposit) moves together; see PaymongoCheckout::settle().
        PaymongoCheckout::settle(
            $payment,
            $resource['attributes']['payment_method_used'] ?? null,
            $sessionPayments,
            $resource['attributes']['payment_intent']['id'] ?? null,
        );
    }

    protected function verifySignature(string $payload, string $signatureHeader, bool $livemode = false): bool
    {
        $secret = config('services.paymongo.webhook_secret');

        if (empty($secret)) {
            Log::warning('PayMongo webhook: PAYMONGO_WEBHOOK_SECRET is not set');

            return false;
        }

        // PayMongo signature format: t=<timestamp>,te=<test_signature>,li=<live_signature>
        // (docs: https://developers.paymongo.com/docs/securing-webhook)
        $parts = collect(explode(',', $signatureHeader))
            ->filter(fn ($part) => str_contains($part, '='))
            ->mapWithKeys(function ($part) {
                [$key, $value] = explode('=', $part, 2);

                return [$key => $value];
            });

        // A live event carries its signature in `li` and leaves `te` empty, and the other way round in test mode.
        $timestamp = $parts->get('t');
        $signature = $parts->get($livemode ? 'li' : 'te');

        if (!$timestamp || !$signature || !ctype_digit($timestamp)) {
            return false;
        }

        // A signed body stays valid forever unless its timestamp is checked, so
        // a captured request could be replayed at any time. Reject anything
        // outside the tolerance window, in either direction.
        $tolerance = (int) config('services.paymongo.webhook_tolerance', 300);

        if (abs(time() - (int) $timestamp) > $tolerance) {
            Log::warning('PayMongo webhook: signature timestamp outside tolerance');

            return false;
        }

        $expectedSignature = hash_hmac('sha256', $timestamp . '.' . $payload, $secret);

        return hash_equals($expectedSignature, $signature);
    }
}
