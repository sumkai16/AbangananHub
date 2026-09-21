<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Payment;
use App\Models\PropertyUnit;
use App\Models\Reservation;
use App\Support\MoveInPaymentBreakdown;

/**
 * Writes the move-in money a walk-in landlord collected at the door.
 *
 * Shared by Landlord\WalkInTenantController and its API twin, which are
 * otherwise near-identical already — the allocation is the part that must not
 * be written twice, because a web-recorded tenancy and a mobile-recorded one
 * disagreeing about what a payment was for would be invisible until a landlord
 * compared two ledgers.
 *
 * Status is always 'Paid', never 'Held': escrow protects a tenant paying a
 * stranger through the platform before they have keys, and this money changed
 * hands in person at a door the tenant was already standing in. `recorded_by`
 * is what keeps a landlord's assertion distinguishable from a PayMongo
 * settlement, and `payout_status` stays null — the platform never touched this
 * money, so there is nothing for it to pay out.
 */
trait RecordsMoveInPayments
{
    /**
     * Splits one collected amount into deposit / first-month rent / advance rent
     * and writes a row for each.
     *
     * Callers must already hold the transaction and the unit lock — these are
     * money rows on a tenancy that is being created in the same breath.
     */
    protected function recordMoveInPayments(
        Reservation $reservation,
        PropertyUnit $unit,
        array $data,
        int $landlordId,
    ): void {
        $breakdown = MoveInPaymentBreakdown::make(
            (float) $data['initial_amount'],
            (float) ($data['agreed_monthly_rent'] ?? $unit->rental_fee),
            (float) ($unit->security_deposit ?? 0),
            $data['move_in_date'],
        );

        // One collection event, split by purpose — so every row carries the
        // same method, date and reference. They are not separate payments and
        // must not be readable as though the tenant paid three times.
        $shared = [
            'reservation_id' => $reservation->reservation_id,
            'payment_method' => $data['payment_method'],
            'status'         => 'Paid',
            'paid_at'        => $data['payment_date'] ?? now(),
            'reference_no'   => $data['reference_no'] ?? null,
            'recorded_by'    => $landlordId,
        ];

        foreach ($breakdown->rows() as $row) {
            Payment::create($shared + $row);
        }
    }
}
