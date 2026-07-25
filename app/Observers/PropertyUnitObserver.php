<?php

namespace App\Observers;

use App\Models\OccupancyActivity;
use App\Models\PropertyUnit;
use App\Models\Reservation;
use Illuminate\Support\Facades\Auth;

class PropertyUnitObserver
{
    /**
     * Log an occupancy activity whenever a unit's availability_status changes.
     * Catches every path: manual edit, reservation approve/cancel, move-in.
     */
    public function updated(PropertyUnit $unit): void
    {
        if (! $unit->wasChanged('availability_status')) {
            return;
        }

        $property = $unit->property;
        if (! $property) {
            return;
        }

        // Tenant relevant to this transition, if any (latest non-terminal reservation)
        $tenantId = Reservation::where('unit_id', $unit->unit_id)
            ->whereNotIn('rental_status', Reservation::TERMINAL_STATUSES)
            ->latest('reservation_id')
            ->value('tenant_id');

        OccupancyActivity::create([
            'landlord_id' => $property->landlord_id,
            'property_id' => $property->property_id,
            'unit_id'     => $unit->unit_id,
            'actor_id'    => Auth::id(),
            'tenant_id'   => $tenantId,
            'from_status' => $unit->getOriginal('availability_status'),
            'to_status'   => $unit->availability_status,
        ]);
    }
}
