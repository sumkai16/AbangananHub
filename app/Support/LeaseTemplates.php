<?php

namespace App\Support;

use App\Models\LeaseTemplate;
use App\Models\Property;
use App\Models\PropertyUnit;
use App\Models\Reservation;

/**
 * Which lease template applies: the unit's, else the property's, else none (the standard clauses).
 * The one place that answers it, so the chat, the walk-in form and the previews never disagree.
 */
class LeaseTemplates
{
    public static function for(?Property $property, ?PropertyUnit $unit = null): ?LeaseTemplate
    {
        $property ??= $unit?->property;
        $template = $unit?->leaseTemplate ?? $property?->leaseTemplate;

        // Assignments are checked on write; this keeps a stray id from ever putting one landlord's lease on another's unit.
        return $template && $template->landlord_id === $property?->landlord_id ? $template : null;
    }

    public static function forReservation(Reservation $reservation): ?LeaseTemplate
    {
        $reservation->loadMissing(['property.leaseTemplate', 'unit.leaseTemplate']);

        return self::for($reservation->property, $reservation->unit);
    }
}
