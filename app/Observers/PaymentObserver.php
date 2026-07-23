<?php

namespace App\Observers;

use App\Events\PaymentStatusUpdated;
use App\Models\Notification;
use App\Models\Payment;

/**
 * Broadcasts every payment status transition, and raises the notifications
 * for the one that funds the escrow (Pending -> Held).
 *
 * Payment settles from four places — the PayMongo webhook, the checkout
 * return handler that polls PayMongo when the webhook hasn't landed yet,
 * admin release, and the move-in deadline command. Each dispatched the
 * broadcast itself, and the return handler never got the call, so paying
 * left the conversation stepper stale until a reload. That path is the
 * only one that runs in local development, since PayMongo cannot reach
 * localhost.
 *
 * Hooking the save instead of each call site means no settle path —
 * present or future — can change the status without both parties being
 * told. Same reasoning as ReservationObserver, which catches
 * rental_status the same way.
 *
 * System messages stay with the callers: their wording is specific to the
 * transition that produced them, and there is no generic sentence that
 * would be right for all four.
 */
class PaymentObserver
{
    /**
     * Every release path wraps its update in a transaction and deliberately
     * broadcast *after* the commit — a payout announced inside the transaction
     * could still be rolled back underneath the broadcast, leaving both parties
     * told about money that never moved. Handlers stay deferred until the
     * enclosing transaction commits, and run immediately when there isn't one,
     * so moving the dispatch in here keeps that ordering rather than quietly
     * dropping it back inside the transaction.
     */
    public bool $afterCommit = true;

    public function updated(Payment $payment): void
    {
        if (! $payment->wasChanged('status')) {
            return;
        }

        PaymentStatusUpdated::dispatch($payment);

        if ($payment->getOriginal('status') === 'Pending' && $payment->status === 'Held') {
            $this->notifyEscrowFunded($payment);
        }
    }

    /**
     * Both parties need to know the money is in escrow: the tenant so they
     * know the payment landed, the landlord so they know to expect a move-in.
     */
    private function notifyEscrowFunded(Payment $payment): void
    {
        $reservation = $payment->reservation;

        if (! $reservation) {
            return;
        }

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
