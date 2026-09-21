<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * A unit is only Occupied while a tenancy backs it. Seeder rows were marked
     * Occupied with no reservation at all; free every such unit.
     */
    public function up(): void
    {
        DB::table('property_units')
            ->where('availability_status', 'Occupied')
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('reservations')
                    ->whereColumn('reservations.unit_id', 'property_units.unit_id')
                    ->where('reservations.rental_status', 'Occupied');
            })
            ->update(['availability_status' => 'Available']);
    }

    public function down(): void
    {
        // Data correction; the previous (tenant-less) Occupied state is not restored.
    }
};
