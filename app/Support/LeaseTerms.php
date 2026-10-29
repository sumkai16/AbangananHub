<?php

namespace App\Support;

use App\Models\LeaseTemplate;
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
        if ($reservation->lease_snapshot) {
            return $reservation->lease_snapshot;
        }

        // Issued before snapshots existed: render live, but never show rent rules the tenant did not agree to.
        return static::snapshot($reservation, withRentRules: false);
    }

    /**
     * What the ledger charges for late rent on this tenancy: grace days and an optional one-time late fee.
     * Read from the frozen snapshot only. A reservation with no snapshot (issued before this existed) gets the
     * platform grace and NO late fee, so a landlord switching a fee on never charges a tenant who never agreed.
     *
     * @return array{grace_days:int, late_fee:?array{type:string,value:float}}
     */
    public static function rentRules(Reservation $reservation): array
    {
        $snapshot = $reservation->lease_snapshot ?: [];
        $fee = $snapshot['late_fee'] ?? null;

        return [
            'grace_days' => (int) ($snapshot['grace_days'] ?? config('rentals.rent_overdue_grace_days')),
            'late_fee'   => is_array($fee) && isset($fee['type'], $fee['value']) ? ['type' => $fee['type'], 'value' => (float) $fee['value']] : null,
        ];
    }

    /**
     * Build the terms from the reservation's current data. Call this at the
     * moment the lease is issued and store the result.
     */
    /**
     * $template is the lease template the agreement is issued on (LeaseTemplates::forReservation). It is passed in,
     * never looked up here, so a late snapshot of an old tenancy (LeaseController::upload) can't pick up today's template.
     */
    public static function snapshot(Reservation $reservation, bool $withRentRules = true, ?string $body = null, ?LeaseTemplate $template = null): array
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

        $terms = [
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
            // The listing's rules always stand; a template can only add to them, so the lease never contradicts the listing.
            'house_rules' => array_values(array_unique(array_filter([...($property?->house_rules ?? []), ...collect(HouseRules::visible($template?->house_rule_sections))->flatMap(fn ($s) => $s['rules'])->all()]))),
            // The same rules as titled sections (the listing's first), for the lease to print with headings.
            'house_rule_sections' => HouseRules::forSnapshot($property?->house_rules ?? [], $template?->house_rule_sections),
            'notice_days' => (int) ($template?->notice_days ?? config('rentals.lease_notice_days')),
            'grace_days'  => $withRentRules ? (int) ($template?->rent_grace_days ?? $property?->rent_grace_days ?? config('rentals.rent_overdue_grace_days')) : null,
            'late_fee'    => $withRentRules ? static::lateFee($template, $property) : null,
            'extra_terms' => $reservation->agreement_terms_notes,
        ];

        // The landlord's own wording, with merge fields already replaced from the data above and frozen with it.
        // Absent for leases that use the built-in clauses.
        $body = filled($body) ? $body : $template?->body;
        if (filled($body)) {
            $terms['body'] = LeaseBody::resolve($body, $terms);
        }
        // What the tenant must upload before signing, frozen so a later template edit changes nothing already sent.
        if ($required = RequiredDocuments::forSnapshot($template?->required_documents)) {
            $terms['required_documents'] = $required;
        }
        if ($template) {
            $terms['template'] = ['id' => $template->lease_template_id, 'name' => $template->name, 'version' => $template->version ?? 1];
            if (filled($template->welcome_message)) {
                $terms['welcome'] = $template->welcome_message;
            }
        }

        return $terms;
    }

    /**
     * Stable, human-quotable id. A contract people sign needs something to
     * reference it by in a dispute or a message.
     */
    public static function reference(Reservation $reservation): string
    {
        return 'AGR-' . $reservation->created_at->format('Y') . '-' . str_pad($reservation->reservation_id, 5, '0', STR_PAD_LEFT);
    }

    /** The template's late fee wins, including an explicit "no fee"; a template that leaves it blank uses the property's rule. */
    private static function lateFee(?LeaseTemplate $template, $property): ?array
    {
        $fromTemplate = $template?->lateFee();
        if ($fromTemplate !== null) {
            return $fromTemplate ?: null;
        }

        return $property?->late_fee_type && $property->late_fee_value !== null
            ? ['type' => $property->late_fee_type, 'value' => (float) $property->late_fee_value]
            : null;
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
