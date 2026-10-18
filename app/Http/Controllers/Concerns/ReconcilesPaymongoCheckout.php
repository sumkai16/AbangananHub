<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Payment;
use App\Models\Reservation;
use App\Services\PaymongoCheckout;
use Illuminate\Support\Carbon;
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
                'payout_owed_at' => now(),
            ]);

            $label = $payment->billing_period?->format('M Y') ?? 'this period';
            $reservation->postSystemMessage($reservation->tenant->name.' paid rent for '.$label.' online.');
        } else {
            $payment->update($common + ['status' => 'Held']);

            $reservation->postSystemMessage(
                $reservation->tenant->name.' completed the initial payment. Funds are held by AbangananHub.'
            );
        }

        $payment->alertAdminsIfStranded();

        return $payment->fresh();
    }

    /**
     * Clear checkouts the tenant walked away from, so Pay can be pressed again. Call it before looking for a
     * blocking Pending payment. A Pending row used to block a new session forever: nothing ever expired it, and
     * the agreement page hid Pay Now while one existed.
     *
     * Returns a message when the caller must stop instead of opening a new session, or null to carry on:
     *  - it was actually paid (the webhook never arrived): it is settled here and the tenant is told;
     *  - it cannot be confirmed expired: fail safe. A second session next to one that might still pay is a
     *    double charge, which is worse than asking the tenant to retry shortly.
     *
     * Only stale rows are touched. A recent Pending one is left alone and blocks as before.
     *
     * @param  string       $type           'Initial' or 'Monthly'
     * @param  Carbon|null  $billingPeriod  for 'Monthly', the month being paid
     */
    protected function clearStaleCheckouts(Reservation $reservation, string $type, ?Carbon $billingPeriod = null): ?string
    {
        $stale = Payment::where('reservation_id', $reservation->getKey())
            ->where('payment_type', $type)
            ->where('status', 'Pending')
            ->when($billingPeriod, fn ($q) => $q->whereDate('billing_period', $billingPeriod->toDateString()))
            ->get()
            ->filter(fn (Payment $p) => $p->isStalePending());

        foreach ($stale as $payment) {
            // The request died after creating the row and before saving the session id: no money is possible.
            if (! $payment->paymongo_checkout_session_id) {
                $payment->delete();

                continue;
            }

            $reservation->loadMissing('tenant');

            if ($this->reconcilePayment($reservation, $payment)->status !== 'Pending') {
                return 'Your last payment went through after all. Refresh this page to see it.';
            }

            if (! PaymongoCheckout::abandon($payment->paymongo_checkout_session_id)) {
                return 'We could not confirm that your last payment attempt was cancelled. Please try again in a few minutes.';
            }

            $payment->delete();
        }

        return null;
    }
}
