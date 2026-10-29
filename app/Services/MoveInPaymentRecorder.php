<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\PropertyUnit;
use App\Models\Reservation;
use App\Support\MoveInPaymentBreakdown;

/**
 * Writes the move-in money that reached the landlord outside AbangananHub: a walk-in's cash at the
 * door, a landlord recording what a platform tenant handed over, or a tenant's direct payment the
 * landlord confirmed (then $claimId points at the claim).
 *
 * One amount, split by purpose into the security deposit, the first month's rent and any rent paid in
 * advance (see MoveInPaymentBreakdown). The rent rows are 'Monthly' rows carrying a billing_period, which
 * is the shape RentLedger already settles, so month one reads Paid with no ledger change.
 *
 * Status is always 'Paid', never 'Held': the platform never touched this money, so there is nothing to
 * hold, release or pay out (no payout_owed_at). `recorded_by` is what keeps it distinguishable from a
 * PayMongo settlement.
 *
 * Callers must already hold the transaction and the lock on the reservation (or the unit, for a walk-in
 * created in the same breath).
 *
 * @param  array{initial_amount: float|int|string, agreed_monthly_rent?: float|int|string|null, move_in_date: mixed, payment_method: string, payment_date?: mixed, reference_no?: ?string}  $data
 */
class MoveInPaymentRecorder
{
    public static function record(
        Reservation $reservation,
        PropertyUnit $unit,
        array $data,
        int $recordedBy,
        ?int $claimId = null,
    ): int {
        $breakdown = MoveInPaymentBreakdown::make(
            (float) $data['initial_amount'],
            (float) ($data['agreed_monthly_rent'] ?? $unit->rental_fee),
            $reservation->agreedDeposit(),
            $data['move_in_date'],
        );

        // One collection event, split by purpose, so every row carries the same method, date and
        // reference. They are not separate payments and must not read as though the tenant paid three times.
        $shared = [
            'reservation_id' => $reservation->reservation_id,
            'payment_method' => $data['payment_method'],
            'status' => 'Paid',
            'paid_at' => $data['payment_date'] ?? now(),
            'reference_no' => $data['reference_no'] ?? null,
            'recorded_by' => $recordedBy,
            'payment_claim_id' => $claimId,
        ];

        $rows = $breakdown->rows();

        foreach ($rows as $row) {
            Payment::create($shared + $row);
        }

        return count($rows);
    }
}
