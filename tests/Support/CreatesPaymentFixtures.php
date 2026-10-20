<?php

namespace Tests\Support;

use App\Models\Payment;
use App\Models\Payout;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Support\Arr;

/**
 * An occupied tenancy and payment rows of every settlement route, so the money
 * tests read as what happened rather than as column lists. Needs
 * CreatesMarketplaceFixtures and a test that rolls its rows back.
 */
trait CreatesPaymentFixtures
{
    /** @return array{landlord: User, tenant: User, reservation: Reservation} */
    protected function makeTenancy(array $reservation = [], ?User $landlord = null, ?User $tenant = null): array
    {
        config(['broadcasting.default' => 'null']);

        $landlord ??= $this->makeLandlord();
        $tenant ??= $this->makeTenant();
        $unit = $this->makeUnit($this->makeProperty($landlord));

        $reservation = Reservation::create(array_merge([
            'tenant_id'           => $tenant->user_id,
            'property_id'         => $unit->property_id,
            'unit_id'             => $unit->unit_id,
            'rental_status'       => 'Occupied',
            'reservation_date'    => '2026-08-01',
            'target_move_in_date' => '2026-08-01',
            'agreed_monthly_rent' => 5000,
            'rent_due_day'        => 5,
        ], $reservation));

        return compact('landlord', 'tenant', 'reservation');
    }

    protected function makePayment(Reservation $reservation, array $attributes = []): Payment
    {
        // Payout state is derived now (payout_owed_at / payout_id). Tests can still say
        // 'payout_status' => 'Pending Payout' | 'Paid Out' and get the matching rows.
        $payoutStatus = Arr::pull($attributes, 'payout_status');

        if ($payoutStatus !== null) {
            $attributes['payout_owed_at'] ??= now();
        }

        if ($payoutStatus === 'Paid Out') {
            $reservation->loadMissing('property');
            $attributes['payout_id'] ??= Payout::create([
                'landlord_id'      => $reservation->property->landlord_id,
                'amount'           => $attributes['amount'] ?? 5000,
                'payout_reference' => 'TEST-PAYOUT',
                'paid_out_at'      => now(),
            ])->payout_id;
        }

        return Payment::create(array_merge([
            'reservation_id' => $reservation->reservation_id,
            'payment_type'   => 'Monthly',
            'billing_period' => '2026-08-01',
            'amount'         => 5000,
            'payment_method' => 'Cash',
            'status'         => 'Paid',
            'paid_at'        => now(),
        ], $attributes));
    }

    /** A payment the landlord typed in themselves (offline money). */
    protected function makeRecordedPayment(Reservation $reservation, User $landlord, array $attributes = []): Payment
    {
        return $this->makePayment($reservation, array_merge(['recorded_by' => $landlord->user_id], $attributes));
    }

    /** A checkout waiting on PayMongo. */
    protected function makePendingCheckout(Reservation $reservation, string $sessionId, array $attributes = []): Payment
    {
        return $this->makePayment($reservation, array_merge([
            'payment_type'                 => 'Initial',
            'billing_period'               => null,
            'payment_method'               => 'GCash',
            'status'                       => 'Pending',
            'paid_at'                      => null,
            'paymongo_checkout_session_id' => $sessionId,
        ], $attributes));
    }
}
