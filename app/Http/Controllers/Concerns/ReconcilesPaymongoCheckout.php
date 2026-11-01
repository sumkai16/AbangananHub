<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Payment;
use App\Models\Reservation;
use App\Services\PaymentClaims;
use App\Services\PaymongoCheckout;
use App\Support\ServiceFee;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

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

        // Settle only what we asked for; a different amount stays Pending and the admins are told.
        if (! PaymongoCheckout::paidAmountMatches($sessionPayments, $payment)) {
            return $payment;
        }

        PaymongoCheckout::settle(
            $payment,
            $response->json('data.attributes.payment_method_used'),
            $sessionPayments,
            $response->json('data.attributes.payment_intent.id'),
        );

        return $payment->fresh();
    }

    /**
     * What to tell the tenant when PayMongo refuses to open a checkout, by why. A rejected key is our problem, not
     * theirs: say so, point them at the ways that still work, and tell the admins once a day so it gets fixed.
     */
    private function checkoutFailureMessage(\Illuminate\Http\Client\Response $response): string
    {
        if (in_array($response->status(), [401, 403], true)) {
            \App\Services\LandlordPayouts::alertAdmins(
                'paymongo-checkout-refused',
                'Online checkout is failing',
                'PayMongo refused to open a checkout (HTTP '.$response->status().'). Check PAYMONGO_SECRET_KEY in .env, that it is the key for the mode you are in, and that the server was restarted after changing it.',
                route('admin.settings.edit'),
            );

            return 'Online payment is not working right now, and it is not something you did. You can pay '.'your landlord by QR or in cash instead, or try again later. We have been told.';
        }

        if ($response->status() === 429 || $response->serverError()) {
            return 'The payment service is busy right now. Please wait a minute and try again.';
        }

        return 'Could not create payment session. Please try again.';
    }

    /**
     * Refuse to start a checkout beside an "I paid" report the landlord has not decided on yet: the tenant
     * would be paying the same move-in or month twice. Call it inside the lock on the reservation, so a report
     * filed at the same moment (PaymentClaims::file takes the same lock) is seen by one side or the other.
     *
     * @param  string  $claimType  'MoveIn' or 'Monthly'
     *
     * @throws ValidationException
     */
    protected function assertNoPendingClaim(Reservation $locked, string $claimType, ?Carbon $billingPeriod = null): void
    {
        if (PaymentClaims::hasPending($locked, $claimType, $billingPeriod)) {
            throw ValidationException::withMessages(['payment' => [
                'You already told your landlord you paid this. Wait for them to confirm it, or withdraw your report first.',
            ]]);
        }
    }

    /**
     * Open a PayMongo checkout for a move-in (`Initial`) or one month of rent (`Monthly`) and return the payment
     * rows it will fund plus where to send the tenant. Shared by the four checkout endpoints (web and API, move-in
     * and rent), so the rows, the fee and the PayMongo call can never differ between them.
     *
     * Rows are created Pending together in one locked transaction, so a double click or a report filed at the same
     * moment cannot slip a second checkout in: the rent row (carrying the online fee, frozen now) and, for a
     * move-in with a deposit, a `Deposit` row on the same session. They settle, release and refund as one.
     *
     * Failures throw a ValidationException on the `payment` key: the web redirects back with it, the API answers 422.
     *
     * @param  string  $type  'Initial' or 'Monthly'
     * @param  float  $rent  what the landlord is owed for the rent line (Monthly: the period balance, late fee included)
     * @param  string  $busyMessage  shown when another checkout (or an inactive tenancy) blocks this one
     * @return array{0: Payment, 1: string} the lead (rent) row and PayMongo's checkout URL
     *
     * @throws ValidationException
     */
    protected function openCheckout(
        Reservation $reservation,
        string $type,
        ?Carbon $billingPeriod,
        float $rent,
        string $rentLineName,
        string $description,
        string $successUrl,
        string $cancelUrl,
        string $busyMessage,
    ): array {
        $isMoveIn = $type === 'Initial';
        $deposit = $isMoveIn ? round((float) $reservation->agreedDeposit(), 2) : 0.0;
        $fee = ServiceFee::for($rent);

        $rows = DB::transaction(function () use ($reservation, $type, $billingPeriod, $rent, $deposit, $fee, $isMoveIn) {
            // Lock the reservation row so two concurrent requests (e.g. a double click) can't both pass the
            // "no pending payment" check before either has inserted its Payment rows.
            $locked = Reservation::whereKey($reservation->reservation_id)->lockForUpdate()->first();

            if (! $isMoveIn && $locked->rental_status !== 'Occupied') {
                return null;
            }

            $this->assertNoPendingClaim($locked, $isMoveIn ? 'MoveIn' : 'Monthly', $billingPeriod);

            $existingPending = Payment::where('reservation_id', $locked->reservation_id)
                ->where('status', 'Pending')
                ->whereNotNull('paymongo_checkout_session_id')
                ->when(! $isMoveIn, fn ($q) => $q->where('payment_type', 'Monthly')->whereDate('billing_period', $billingPeriod->toDateString()))
                ->exists();

            if ($existingPending) {
                return null;
            }

            // Placeholder rows reserve this reservation for the current request until they carry PayMongo's session id.
            $rows = [Payment::create([
                'reservation_id' => $locked->reservation_id,
                'payment_type' => $type,
                'billing_period' => $billingPeriod,
                'amount' => $rent,
                'service_fee' => $fee,
                'payment_method' => 'GCash',
                'status' => 'Pending',
            ])];

            if ($deposit > 0) {
                $rows[] = Payment::create([
                    'reservation_id' => $locked->reservation_id,
                    'payment_type' => 'Deposit',
                    'amount' => $deposit,
                    'payment_method' => 'GCash',
                    'status' => 'Pending',
                ]);
            }

            return $rows;
        });

        if (! $rows) {
            throw ValidationException::withMessages(['payment' => [$busyMessage]]);
        }

        $lines = [['name' => $rentLineName, 'amount' => $rent]];

        if ($deposit > 0) {
            $lines[] = ['name' => $reservation->unit->unit_name.' — Security deposit', 'amount' => $deposit];
        }

        if ($fee > 0) {
            $lines[] = ['name' => 'Online payment fee', 'amount' => $fee];
        }

        $response = PaymongoCheckout::create($reservation, $lines, $description, $successUrl, $cancelUrl);

        if ($response->failed()) {
            // Say why in the log: the tenant only sees a generic message, and a live account can refuse for reasons
            // (a payment method not switched on yet, an amount below the minimum) that only PayMongo's answer shows.
            Log::error('PayMongo could not create a checkout session', [
                'reservation_id' => $reservation->reservation_id,
                'status' => $response->status(),
                'errors' => $response->json('errors') ?? mb_substr($response->body(), 0, 300),
            ]);

            // Free up the reservation for a retry instead of leaving dead placeholders behind.
            foreach ($rows as $row) {
                $row->delete();
            }

            throw ValidationException::withMessages(['payment' => [$this->checkoutFailureMessage($response)]]);
        }

        $checkout = $response->json('data');

        foreach ($rows as $row) {
            $row->update(['paymongo_checkout_session_id' => $checkout['id']]);
        }

        return [$rows[0], $checkout['attributes']['checkout_url']];
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
     * @param  string  $type  'Initial' or 'Monthly'
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

        // One online move-in is several rows on one session (rent + deposit): look at the checkout once, and clear
        // all of its rows together. Rows without a session id are each their own dead placeholder.
        foreach ($stale->unique(fn (Payment $p) => $p->paymongo_checkout_session_id ?? 'row-'.$p->payment_id) as $payment) {
            // The request died after creating the row and before saving the session id: no money is possible.
            if (! $payment->paymongo_checkout_session_id) {
                $payment->delete();

                continue;
            }

            if ($message = $this->abandonCheckout($reservation, $payment)) {
                return $message;
            }
        }

        return null;
    }

    /**
     * Close one open checkout and drop all of its placeholder rows (rent + deposit share a session). Used for a
     * checkout gone stale and for a tenant who cancels one on purpose. Fails safe: if it was in fact paid it is
     * settled and the tenant told; if PayMongo cannot confirm the session is closed, nothing is deleted.
     *
     * @return string|null a message when the caller must stop, null when the checkout is gone
     */
    protected function abandonCheckout(Reservation $reservation, Payment $payment): ?string
    {
        $reservation->loadMissing('tenant');

        if ($this->reconcilePayment($reservation, $payment)->status !== 'Pending') {
            return 'Your last payment went through after all. Refresh this page to see it.';
        }

        if (! PaymongoCheckout::abandon($payment->paymongo_checkout_session_id)) {
            return 'We could not confirm that your last payment attempt was cancelled. Please try again in a few minutes.';
        }

        Payment::where('paymongo_checkout_session_id', $payment->paymongo_checkout_session_id)->delete();

        return null;
    }

    /** The checkout this tenant started and has not finished: the newest Pending row of that type with a session. */
    protected function findOpenCheckout(Reservation $reservation, string $type): ?Payment
    {
        return Payment::where('reservation_id', $reservation->getKey())
            ->where('payment_type', $type)
            ->where('status', 'Pending')
            ->whereNotNull('paymongo_checkout_session_id')
            ->latest('payment_id')
            ->first();
    }
}
