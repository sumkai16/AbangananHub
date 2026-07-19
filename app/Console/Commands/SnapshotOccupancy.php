<?php

namespace App\Console\Commands;

use App\Models\OccupancySnapshot;
use App\Models\Property;
use App\Models\PropertyUnit;
use App\Services\OccupancyRateCalculator;
use Illuminate\Console\Command;

class SnapshotOccupancy extends Command
{
    protected $signature = 'occupancy:snapshot';

    protected $description = 'Record a daily occupancy snapshot per landlord (feeds the occupancy trend chart)';

    public function handle(): int
    {
        $landlordIds = Property::query()->distinct()->pluck('landlord_id');

        foreach ($landlordIds as $landlordId) {
            $propertyIds = Property::where('landlord_id', $landlordId)->pluck('property_id');
            $units = PropertyUnit::whereIn('property_id', $propertyIds)->get();

            OccupancySnapshot::updateOrCreate(
                ['landlord_id' => $landlordId, 'snapshot_date' => today()],
                [
                    'total_units'       => $units->count(),
                    'available_units'   => $units->where('availability_status', 'Available')->count(),
                    'reserved_units'    => $units->where('availability_status', 'Reserved')->count(),
                    'occupied_units'    => $units->where('availability_status', 'Occupied')->count(),
                    'maintenance_units' => $units->where('availability_status', 'Maintenance')->count(),
                    'occupancy_rate'    => OccupancyRateCalculator::forLandlord($landlordId),
                ]
            );
        }

        $this->info("Occupancy snapshots recorded for {$landlordIds->count()} landlord(s).");

        return self::SUCCESS;
    }
}
