<?php

namespace App\Observers;

use App\Models\PropertyActivity;
use App\Models\PropertyUnit;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * The tenancy events of a property's history: an inquiry, an agreement sent and signed, moving in and out,
 * and the ways a reservation ends. It watches `rental_status` only (every other column is noise), and sits
 * beside ReservationObserver, which sends the notifications.
 *
 * Wording is neutral ("Ana Reyes moved in to Bed A") because the role chip beside it already says who acted,
 * and a tenant is named by name only.
 */
class ReservationActivityObserver
{
    public function created(Reservation $reservation): void
    {
        $this->note($reservation, forceTenant: $reservation->rental_status === 'Inquiry');
    }

    public function updated(Reservation $reservation): void
    {
        if ($reservation->wasChanged('rental_status')) {
            $this->note($reservation);
        }
    }

    private function note(Reservation $reservation, bool $forceTenant = false): void
    {
        PropertyActivity::safely(function () use ($reservation, $forceTenant) {
            if (! $reservation->property_id) {
                return;
            }

            $tenant = PropertyActivity::nameOf($reservation->tenant_id);
            $unit = $this->unitLabel($reservation);

            [$action, $summary] = match ($reservation->rental_status) {
                'Inquiry'                 => ['reservation.inquiry', "{$tenant} sent an inquiry about {$unit}"],
                'Pending Rental Agreement' => ['reservation.agreement_sent', "Rental agreement sent to {$tenant} for {$unit}"],
                'Rental Agreement Signed' => ['reservation.signed', "{$tenant} signed the rental agreement for {$unit}"],
                'Occupied'                => ['reservation.moved_in', "{$tenant} moved in to {$unit}"],
                'Completed'               => ['reservation.moved_out', "{$tenant} moved out of {$unit}"],
                'Cancelled'               => ['reservation.cancelled', "{$tenant}'s reservation for {$unit} was cancelled"],
                'Rejected'                => ['reservation.rejected', "{$tenant}'s inquiry about {$unit} was declined"],
                default                   => [null, null],
            };

            if (! $action) {
                return;
            }

            $options = [
                'unit_id'  => $reservation->unit_id,
                'metadata' => array_filter([
                    'reservation_id'  => $reservation->reservation_id,
                    'conversation_id' => $reservation->conversation_id,
                    'to_status'       => $reservation->rental_status,
                ]),
            ];

            if ($forceTenant) {
                // An inquiry is always the tenant's, whoever's request carried it.
                $options['actor'] = User::query()->whereKey($reservation->tenant_id)->first(['user_id', 'first_name', 'last_name']);
                $options['role'] = 'tenant';
            }

            PropertyActivity::record($reservation->property_id, $action, $summary, $options);
        });
    }

    private function unitLabel(Reservation $reservation): string
    {
        $label = $reservation->unit_id
            ? PropertyUnit::query()->whereKey($reservation->unit_id)->value('unit_label')
            : null;

        return $label ? '"'.Str::limit((string) $label, 60).'"' : 'the property';
    }
}
