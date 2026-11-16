<?php

namespace App\Observers;

use App\Models\OccupancyActivity;
use App\Models\PropertyActivity;
use App\Models\PropertyUnit;
use App\Models\Reservation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class PropertyUnitObserver
{
    /** Column => the words the history line uses. Availability is left out: the reservation lines say why it moved. */
    private const FIELD_LABELS = [
        'unit_label'        => 'name',
        'floor'             => 'floor',
        'bedrooms'          => 'bedrooms',
        'bathrooms'         => 'bathrooms',
        'floor_area_sqm'    => 'floor area',
        'furnishing_status' => 'furnishing',
        'description'       => 'description',
        'rental_fee'        => 'rent',
        'security_deposit'  => 'deposit',
        'occupancy_limit'   => 'capacity',
        'lease_template_id' => 'rental policy',
    ];

    public function created(PropertyUnit $unit): void
    {
        PropertyActivity::safely(function () use ($unit) {
            if (PropertyActivity::isDraft($unit->property_id)) {
                return;
            }

            PropertyActivity::record($unit->property_id, 'unit.added', 'Added unit '.$this->label($unit), ['unit_id' => $unit->unit_id]);
        });
    }

    /**
     * Log an occupancy activity whenever a unit's availability_status changes.
     * Catches every path: manual edit, reservation approve/cancel, move-in.
     * Then the property's own history: edits and the admin's decision on the unit.
     */
    public function updated(PropertyUnit $unit): void
    {
        $this->recordOccupancy($unit);

        PropertyActivity::safely(function () use ($unit) {
            if (PropertyActivity::isDraft($unit->property_id)) {
                return;
            }

            $this->recordDecision($unit);
            $this->recordEdit($unit);
        });
    }

    public function deleted(PropertyUnit $unit): void
    {
        PropertyActivity::safely(function () use ($unit) {
            if (PropertyActivity::isDraft($unit->property_id)) {
                return;
            }

            PropertyActivity::record($unit->property_id, 'unit.removed', 'Removed unit '.$this->label($unit), ['unit_id' => $unit->unit_id]);
        });
    }

    private function recordOccupancy(PropertyUnit $unit): void
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

    private function recordDecision(PropertyUnit $unit): void
    {
        if (! $unit->wasChanged('verification_status')) {
            return;
        }

        $reason = trim((string) $unit->rejection_reason);

        match ($unit->verification_status) {
            'Approved' => PropertyActivity::record($unit->property_id, 'unit.approved', 'Unit '.$this->label($unit).' approved', ['unit_id' => $unit->unit_id]),
            'Rejected' => PropertyActivity::record(
                $unit->property_id,
                'unit.rejected',
                'Unit '.$this->label($unit).' rejected'.($reason !== '' ? ': '.Str::limit($reason, 160) : ''),
                ['unit_id' => $unit->unit_id, 'metadata' => array_filter(['reason' => $reason])],
            ),
            default => null, // Pending is told by the edit line
        };
    }

    private function recordEdit(PropertyUnit $unit): void
    {
        $fields = collect(array_keys($unit->getChanges()))
            ->map(fn ($column) => self::FIELD_LABELS[$column] ?? null)
            ->filter()->unique()->values()->all();

        $requeued = $unit->wasChanged('verification_status') && $unit->verification_status === 'Pending';

        if ($fields === [] && ! $requeued) {
            return;
        }

        if ($fields === []) {
            PropertyActivity::record($unit->property_id, 'unit.requeued', 'Sent unit '.$this->label($unit).' back for admin review', ['unit_id' => $unit->unit_id]);

            return;
        }

        PropertyActivity::record(
            $unit->property_id,
            'unit.updated',
            'Edited unit '.$this->label($unit).' ('.implode(', ', $fields).')'.($requeued ? '. It went back for admin review.' : ''),
            ['unit_id' => $unit->unit_id, 'metadata' => ['fields' => $fields, 'requeued' => $requeued]],
        );
    }

    private function label(PropertyUnit $unit): string
    {
        return '"'.Str::limit((string) $unit->unit_label, 60).'"';
    }
}
