<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Notification;
use App\Models\Payment;
use App\Models\PaymentClaim;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Every rule for money that is paid to the landlord directly (plans/direct-payments-record-and-confirm.md).
 *
 * The tenant reports a payment ("I paid"), the landlord confirms or rejects it, and only a confirmation
 * writes ordinary Paid rows. Each method runs in one transaction on the locked reservation and re-checks
 * state under that lock, so a double click or two people acting at once cannot write the money twice.
 * Callers authorize who may act (ReservationPolicy); this class only guards the state.
 *
 * Nothing here holds money, so there is no escrow, no payout and no clock: confirmed rows never get
 * `payout_owed_at`.
 */
class PaymentClaims
{
    /**
     * The tenant reports a payment.
     *
     * @param  array{claim_type: string, billing_period?: ?string, amount: numeric, payment_method: string, reference_no: string, paid_on: string, note?: ?string}  $data
     */
    public static function file(Reservation $reservation, User $tenant, array $data): PaymentClaim
    {
        return DB::transaction(function () use ($reservation, $tenant, $data) {
            $locked = self::lock($reservation);

            if ($locked->tenant_id !== $tenant->user_id) {
                abort(403);
            }

            $type = $data['claim_type'];
            $billingPeriod = null;

            if ($type === 'MoveIn') {
                self::assertMoveInOpen($locked);
            } else {
                if ($locked->rental_status !== 'Occupied') {
                    self::fail('Rent can only be reported while your tenancy is running.');
                }

                $ledger = RentLedger::for($locked);
                $asked = Carbon::parse($data['billing_period']);
                // A month that is due, or the single month right after the last one when paying early.
                $period = $ledger->unsettledPeriods()->first(fn ($p) => $p['period']->isSameMonth($asked))
                    ?? collect([$ledger->advancePeriod()])->filter()->first(fn ($p) => $p['period']->isSameMonth($asked));

                if (! $period) {
                    self::fail('That month is already paid.');
                }

                $billingPeriod = $period['period']->copy()->startOfMonth();
            }

            if (self::freshCheckoutOpen($locked, $type, $billingPeriod)) {
                self::fail('An online payment is still being processed for this. Wait for it to finish, then try again.');
            }

            $alreadyPending = $locked->paymentClaims()->pending()
                ->where('claim_type', $type)
                ->when($billingPeriod, fn ($q) => $q->whereDate('billing_period', $billingPeriod))
                ->exists();

            if ($alreadyPending) {
                self::fail('You already reported this payment. Wait for your landlord to confirm it, or withdraw it first.');
            }

            $claim = $locked->paymentClaims()->create([
                'claim_type' => $type,
                'billing_period' => $billingPeriod,
                'amount' => round((float) $data['amount'], 2),
                'payment_method' => $data['payment_method'],
                'reference_no' => trim($data['reference_no']),
                'paid_on' => $data['paid_on'],
                'note' => $data['note'] ?? null,
                'status' => 'Pending',
                'filed_by' => $tenant->user_id,
            ]);

            $label = $type === 'MoveIn' ? 'move-in payment' : 'rent for '.$billingPeriod->format('F Y');
            $tenantName = $tenant->first_name ?? 'Your tenant';

            Notification::notify(
                $locked->property?->landlord_id,
                'payment',
                'Payment to confirm',
                "{$tenantName} says they paid ₱".number_format((float) $claim->amount, 2)." ({$label}) by {$claim->payment_method}, ref {$claim->reference_no}. Check your account, then confirm.",
                self::decisionLink($locked),
                $locked->conversation_id,
            );

            return $claim;
        });
    }

    /** The landlord confirms: the money is real, so write the rows. */
    public static function confirm(PaymentClaim $claim, User $landlord): PaymentClaim
    {
        return DB::transaction(function () use ($claim, $landlord) {
            [$locked, $claim] = self::lockPending($claim, $landlord);

            if ($claim->claim_type === 'MoveIn') {
                self::assertMoveInOpen($locked);

                // Rent starts the day they paid when no move-in date was ever agreed. Pinned here so a later
                // confirmation of occupancy cannot move the months the rows below were written against.
                if (! $locked->target_move_in_date) {
                    $locked->target_move_in_date = $claim->paid_on;
                }

                MoveInPaymentRecorder::record($locked, $locked->unit, [
                    'initial_amount' => $claim->amount,
                    'agreed_monthly_rent' => $locked->monthlyRent(),
                    'move_in_date' => $locked->tenancyStartDate()?->toDateString() ?? $claim->paid_on->toDateString(),
                    'payment_method' => $claim->payment_method,
                    'payment_date' => $claim->paid_on,
                    'reference_no' => $claim->reference_no,
                ], $landlord->user_id, $claim->payment_claim_id);

                $locked->move_in_paid_at = now();
                $locked->save();
            } else {
                if ($locked->rental_status !== 'Occupied') {
                    self::fail('This tenancy is no longer running, so the payment cannot be confirmed.');
                }

                RentPaymentRecorder::record($locked, [
                    'payment_type' => 'Monthly',
                    'billing_period' => $claim->billing_period->toDateString(),
                    'amount' => $claim->amount,
                    'payment_method' => $claim->payment_method,
                    'paid_at' => $claim->paid_on->toDateString(),
                    'reference_no' => $claim->reference_no,
                    'payment_notes' => $claim->note,
                    'payment_claim_id' => $claim->payment_claim_id,
                ], $landlord->user_id);
            }

            $claim->update(['status' => 'Confirmed', 'decided_by' => $landlord->user_id, 'decided_at' => now()]);

            AuditLog::record(
                'payment_claim.confirm',
                '₱'.number_format((float) $claim->amount, 2).' payment confirmed on '.($locked->property?->title ?? 'a property'),
                $claim,
                null,
                ['reservation_id' => $locked->reservation_id, 'claim_type' => $claim->claim_type, 'reference_no' => $claim->reference_no],
            );

            $locked->postSystemMessage($locked->tenant->name.'\'s payment of ₱'.number_format((float) $claim->amount, 2).' was confirmed by the landlord.');

            Notification::notify(
                $locked->tenant_id,
                'payment',
                'Payment confirmed',
                'Your landlord confirmed your ₱'.number_format((float) $claim->amount, 2).' payment.',
                $claim->claim_type === 'MoveIn' ? route('agreements.show', $locked) : route('tenancy.show', $locked),
                $locked->conversation_id,
            );

            return $claim;
        });
    }

    /** The landlord says it did not arrive. The tenant sees the reason and can report again. */
    public static function reject(PaymentClaim $claim, User $landlord, string $reason): PaymentClaim
    {
        return DB::transaction(function () use ($claim, $landlord, $reason) {
            [$locked, $claim] = self::lockPending($claim, $landlord);

            $claim->update([
                'status' => 'Rejected',
                'decided_by' => $landlord->user_id,
                'decided_at' => now(),
                'rejection_reason' => $reason,
            ]);

            AuditLog::record(
                'payment_claim.reject',
                '₱'.number_format((float) $claim->amount, 2).' payment report rejected on '.($locked->property?->title ?? 'a property'),
                $claim,
                $reason,
                ['reservation_id' => $locked->reservation_id, 'claim_type' => $claim->claim_type, 'reference_no' => $claim->reference_no],
            );

            Notification::notify(
                $locked->tenant_id,
                'payment',
                'Payment not confirmed',
                'Your landlord could not confirm your ₱'.number_format((float) $claim->amount, 2).' payment: '.$reason,
                $claim->claim_type === 'MoveIn' ? route('agreements.show', $locked) : route('tenancy.show', $locked),
                $locked->conversation_id,
            );

            return $claim;
        });
    }

    /** The tenant takes their own pending report back (a typo, or they paid another way). */
    public static function withdraw(PaymentClaim $claim, User $tenant): PaymentClaim
    {
        return DB::transaction(function () use ($claim, $tenant) {
            $locked = self::lock($claim->reservation);

            if ($locked->tenant_id !== $tenant->user_id) {
                abort(403);
            }

            $claim = PaymentClaim::whereKey($claim->getKey())->lockForUpdate()->firstOrFail();

            if (! $claim->isPending()) {
                self::fail('This payment report was already answered.');
            }

            $claim->update(['status' => 'Withdrawn', 'decided_at' => now()]);

            return $claim;
        });
    }

    /**
     * The landlord records move-in money themselves (cash at the door, or a transfer they already saw)
     * without the tenant reporting it first. Any pending move-in report is withdrawn so the same money is
     * never counted twice.
     *
     * @param  array{initial_amount: numeric, payment_method: string, payment_date: string, reference_no?: ?string}  $data
     */
    public static function recordMoveIn(Reservation $reservation, User $landlord, array $data): void
    {
        DB::transaction(function () use ($reservation, $landlord, $data) {
            $locked = self::lock($reservation);

            if ($locked->property?->landlord_id !== $landlord->user_id) {
                abort(403);
            }

            self::assertMoveInOpen($locked);

            if (! $locked->target_move_in_date) {
                $locked->target_move_in_date = $data['payment_date'];
            }

            $locked->paymentClaims()->pending()->where('claim_type', 'MoveIn')
                ->update(['status' => 'Withdrawn', 'decided_at' => now()]);

            MoveInPaymentRecorder::record($locked, $locked->unit, $data + [
                'agreed_monthly_rent' => $locked->monthlyRent(),
                'move_in_date' => $locked->tenancyStartDate()?->toDateString(),
            ], $landlord->user_id);

            $locked->move_in_paid_at = now();
            $locked->save();

            AuditLog::record(
                'move_in_payment.record',
                '₱'.number_format((float) $data['initial_amount'], 2).' move-in payment recorded on '.($locked->property?->title ?? 'a property'),
                $locked,
                null,
                ['reservation_id' => $locked->reservation_id, 'payment_method' => $data['payment_method']],
            );

            Notification::notify(
                $locked->tenant_id,
                'payment',
                'Payment recorded',
                'Your landlord recorded your ₱'.number_format((float) $data['initial_amount'], 2).' move-in payment.',
                route('agreements.show', $locked),
                $locked->conversation_id,
            );
        });
    }

    // ─── Guards ──────────────────────────────────────────────

    private static function lock(Reservation $reservation): Reservation
    {
        return Reservation::whereKey($reservation->getKey())->lockForUpdate()->firstOrFail();
    }

    /** @return array{0: Reservation, 1: PaymentClaim} the locked reservation and the claim, re-read under the lock */
    private static function lockPending(PaymentClaim $claim, User $landlord): array
    {
        $locked = self::lock($claim->reservation);

        if ($locked->property?->landlord_id !== $landlord->user_id) {
            abort(403);
        }

        $claim = PaymentClaim::whereKey($claim->getKey())->lockForUpdate()->firstOrFail();

        if (! $claim->isPending()) {
            self::fail('This payment report was already answered.');
        }

        return [$locked, $claim];
    }

    /** Move-in money can be reported or recorded once, while the agreement is signed and nothing is held. */
    private static function assertMoveInOpen(Reservation $locked): void
    {
        if ($locked->rental_status !== 'Rental Agreement Signed') {
            self::fail('The agreement has to be signed, and the move-in not yet confirmed, to report a move-in payment.');
        }

        if ($locked->move_in_paid_at !== null || $locked->hasSettledInitialPayment()) {
            self::fail('The move-in payment is already settled.');
        }

        if (! $locked->unit) {
            self::fail('This unit is no longer available.');
        }
    }

    /** A PayMongo checkout the tenant started and may still pay (only possible while online payments are on). */
    private static function freshCheckoutOpen(Reservation $locked, string $type, ?Carbon $billingPeriod): bool
    {
        $cutoff = now()->subMinutes((int) config('rentals.checkout_stale_minutes', 30));

        return Payment::where('reservation_id', $locked->reservation_id)
            ->where('status', 'Pending')
            ->whereNotNull('paymongo_checkout_session_id')
            ->where('created_at', '>=', $cutoff)
            ->where('payment_type', $type === 'MoveIn' ? 'Initial' : 'Monthly')
            ->when($billingPeriod, fn ($q) => $q->whereDate('billing_period', $billingPeriod))
            ->exists();
    }

    private static function decisionLink(Reservation $locked): string
    {
        return $locked->conversation_id
            ? route('conversations.index', ['active' => $locked->conversation_id])
            : route('landlord.tenancies.show', $locked);
    }

    private static function fail(string $message): never
    {
        throw ValidationException::withMessages(['payment' => [$message]]);
    }
}
