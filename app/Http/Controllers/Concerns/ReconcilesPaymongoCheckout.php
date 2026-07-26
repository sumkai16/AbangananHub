<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Payment;
use App\Models\Reservation;
use Illuminate\Support\Facades\Http;

/**
 * PayMongo can't reach localhost, so in local dev the webhook never fires and
 * this poll-fallback is the *only* path that settles a payment — the exact
 * gap that once left PaymentObserver un-dispatched (see ARCHITECTURE.md,
 * "PaymentObserver broadcasts payment transitions"). One implementation
 * shared by the web return-handler and the API reconcile endpoint, so they
 * can't drift the way the four settle sites once did.
 */
trait ReconcilesPaymongoCheckout
{
    /**
     * If $payment is still Pending and PayMongo now says otherwise, settle it
     * and post the matching system message. Returns the (possibly updated)
     * payment unchanged if there's nothing to reconcile yet.
     */
    protected function reconcilePayment(Reservation $reservation, ?Payment $payment): ?Payment
    {
        if (! $payment || $payment->status !== 'Pending' || ! $payment->paymongo_checkout_session_id) {
            return $payment;
        }

        $response = Http::withBasicAuth(config('services.paymongo.secret_key'), '')
            ->get("https://api.paymongo.com/v1/checkout_sessions/{$payment->paymongo_checkout_session_id}");

        if (! $response->ok()) {
            return $payment;
        }

        $sessionStatus = $response->json('data.attributes.status');
        $sessionPayments = $response->json('data.attributes.payments') ?? [];

        if ($sessionStatus !== 'paid' && count($sessionPayments) === 0) {
            return $payment;
        }

        $method = Payment::resolvePaymongoMethod(
            $response->json('data.attributes.payment_method_used')
                ?? $sessionPayments[0]['attributes']['source']['type'] ?? null
        ) ?? $payment->payment_method;

        $common = [
            'payment_method'              => $method,
            'paymongo_payment_intent_id'  => $response->json('data.attributes.payment_intent.id'),
            'paymongo_payment_id'         => $sessionPayments[0]['id'] ?? null,
            'paid_at'                     => now(),
        ];

        if ($payment->payment_type === 'Monthly') {
            $payment->update($common + [
                'status'        => 'Paid',
                'payout_status' => 'Pending Payout',
            ]);

            $label = $payment->billing_period?->format('M Y') ?? 'this period';
            $reservation->postSystemMessage($reservation->tenant->name.' paid rent for '.$label.' online.');
        } else {
            $payment->update($common + ['status' => 'Held']);

            $reservation->postSystemMessage(
                $reservation->tenant->name.' completed the initial payment. Funds are held by AbangananHub.'
            );
        }

        return $payment->fresh();
    }
}
