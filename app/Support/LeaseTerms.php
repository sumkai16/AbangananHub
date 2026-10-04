<?php

namespace App\Support;

use App\Models\Reservation;

/**
 * The terms of a tenancy's lease, as one plain array that
 * resources/views/leases/_document.blade.php renders.
 *
 * Frozen into reservations.lease_snapshot when the lease is issued (landlord
 * sends the agreement, or saves a walk-in), because a signed contract must
 * not change when the property is edited later — house rules, deposit and
 * utilities all live on rows the landlord can edit at any time. Reservations
 * issued before this existed have no snapshot and render live, as they
 * always did. See plans/formal-lease-agreement.md.
 */
class LeaseTerms
{
    private const UTILITIES = [
        'water_included'            => 'Water',
        'electricity_included'      => 'Electricity',
        'internet_included'         => 'Internet',
        'association_fees_included' => 'Association dues',
    ];

    /**
     * The stored snapshot, or a live build for rows issued before snapshots.
     */
    public static function for(Reservation $reservation): array
    {
        return $reservation->lease_snapshot ?: static::snapshot($reservation);
    }

    /**
     * Build the terms from the reservation's current data. Call this at the
     * moment the lease is issued and store the result.
     */
    public static function snapshot(Reservation $reservation): array
    {
        $reservation->loadMissing(['property.landlord', 'unit', 'tenant']);

        $property = $reservation->property;
        $landlord = $property?->landlord;
        $tenant = $reservation->tenant;

        $included = [];
        $excluded = [];
        foreach (self::UTILITIES as $column => $label) {
            $value = $property?->{$column};
            if ($value === true) {
                $included[] = $label;
            } elseif ($value === false) {
                $excluded[] = $label;
            }
        }

        return [
            'reference'   => static::reference($reservation),
            'issued_at'   => now()->toIso8601String(),
            'landlord'    => static::party($landlord),
            'tenant'      => static::party($tenant),
            'property'    => [
                'title'   => $property?->title,
                'address' => $property?->address,
                'type'    => $property?->property_type,
            ],
            'unit'        => $reservation->unit?->unit_label,
            'start'       => $reservation->target_move_in_date?->toDateString(),
            'end'         => $reservation->target_move_out_date?->toDateString(),
            'monthly_rent' => $reservation->monthlyRent(),
            'due_day'     => $reservation->rentDueDay(),
            'deposit'     => $reservation->unit?->security_deposit !== null ? (float) $reservation->unit->security_deposit : null,
            'occupants'   => $reservation->occupants_count,
            'utilities'   => [
                'included'           => $included,
                'not_included'       => $excluded,
                'separately_metered' => $property?->utilities_separately_metered,
            ],
            'house_rules' => array_values($property?->house_rules ?? []),
            'notice_days' => (int) config('rentals.lease_notice_days'),
            'extra_terms' => $reservation->agreement_terms_notes,
        ];
    }

    /**
     * Stable, human-quotable id. A contract people sign needs something to
     * reference it by in a dispute or a message.
     */
    public static function reference(Reservation $reservation): string
    {
        return 'AGR-' . $reservation->created_at->format('Y') . '-' . str_pad($reservation->reservation_id, 5, '0', STR_PAD_LEFT);
    }

    private static function party($user): array
    {
        return [
            'name'  => trim(($user?->first_name ?? '') . ' ' . ($user?->last_name ?? '')),
            'email' => $user?->email,
            'phone' => $user?->contact_number,
        ];
    }
}
