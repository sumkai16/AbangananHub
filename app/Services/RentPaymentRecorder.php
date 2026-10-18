<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\Payment;
use App\Models\Reservation;
use App\Support\RentPaymentAllocator;
use Illuminate\Support\Carbon;

/**
 * Writes the rows for one landlord-recorded payment. The web form and the
 * mobile API both call this, so a lump of rent is spread over months the same
 * way on both. Before it existed the API wrote one row on the chosen month,
 * which is the bug RentPaymentAllocator was written to prevent.
 *
 * The caller owns the transaction, the lock on $reservation and the checks
 * that it is still Occupied; this only builds and saves rows.
 */
class RentPaymentRecorder
{
    /**
     * @param  array{payment_type: string, billing_period?: ?string, amount: numeric-string|float|int, payment_method: string, paid_at: string, reference_no?: ?string, payment_notes?: ?string, replaces_payment_id?: int|string|null}  $data  validated input
     * @return int the number of Payment rows written
     */
    public static function record(Reservation $locked, array $data, int $recordedBy): int
    {
        // Scoped to this reservation and to an actually-voided row: without the
        // scope this is an IDOR that lets one landlord point a payment at
        // another landlord's row. `exists:` alone would not catch that.
        $replaces = ! empty($data['replaces_payment_id'])
            ? Payment::where('payment_id', $data['replaces_payment_id'])
                ->where('reservation_id', $locked->reservation_id)
                ->where('status', 'Voided')
                ->value('payment_id')
            : null;

        $shared = [
            'reservation_id' => $locked->reservation_id,
            'payment_method' => $data['payment_method'],
            'status' => 'Paid',
            'paid_at' => $data['paid_at'],
            'reference_no' => $data['reference_no'] ?? null,
            'payment_notes' => $data['payment_notes'] ?? null,
            'recorded_by' => $recordedBy,
            'replaces_payment_id' => $replaces,
        ];

        if ($data['payment_type'] !== 'Monthly') {
            Payment::create($shared + [
                'payment_type' => $data['payment_type'],
                'billing_period' => null,
                'amount' => $data['amount'],
            ]);

            self::notifyTenant($locked, (float) $data['amount']);

            return 1;
        }

        $startMonth = Carbon::parse($data['billing_period'])->startOfMonth();

        $rows = RentPaymentAllocator::allocate(
            RentLedger::for($locked)->periods(),
            $startMonth,
            $locked->monthlyRent(),
            (float) $data['amount']
        );

        // Nothing to place only when the amount is not positive, which the
        // controllers' validation already refuses. Kept so a landlord's entry
        // is never silently dropped if that ever changes.
        if ($rows === []) {
            $rows = [['billing_period' => $startMonth, 'amount' => (float) $data['amount']]];
        }

        foreach ($rows as $row) {
            Payment::create($shared + [
                'payment_type' => 'Monthly',
                'billing_period' => $row['billing_period'],
                'amount' => $row['amount'],
            ]);
        }

        self::notifyTenant($locked, (float) $data['amount']);

        return count($rows);
    }

    // One notification per recording, however many months it was spread over; walk-ins have no login.
    private static function notifyTenant(Reservation $locked, float $amount): void
    {
        $locked->loadMissing('tenant');

        Notification::notify(
            $locked->tenant?->is_walk_in ? null : $locked->tenant_id,
            'payment',
            'Payment recorded',
            'Your landlord recorded a payment of ₱'.number_format($amount, 2).' on your tenancy.',
            route('tenancy.show', $locked),
        );
    }
}
