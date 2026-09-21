<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // scope/category must be populated before this runs — invoke the
        // seeder directly rather than relying on a separate `db:seed` step,
        // so `php artisan migrate` alone leaves the system correct.
        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\AmenitySeeder', '--force' => true]);

        $propertyScopedAmenityIds = DB::table('amenities')
            ->where('scope', 'property')
            ->pluck('amenity_id');

        $rows = DB::table('unit_amenities')
            ->join('property_units', 'unit_amenities.unit_id', '=', 'property_units.unit_id')
            ->whereIn('unit_amenities.amenity_id', $propertyScopedAmenityIds)
            ->select('property_units.property_id', 'unit_amenities.amenity_id')
            ->get();

        foreach ($rows as $row) {
            DB::table('property_amenities')->insertOrIgnore([
                'property_id' => $row->property_id,
                'amenity_id'  => $row->amenity_id,
            ]);
        }

        DB::table('unit_amenities')
            ->whereIn('amenity_id', $propertyScopedAmenityIds)
            ->delete();
    }

    /**
     * Not reversible: collapsing several units' tags onto one property loses
     * which unit each came from, so there is no faithful way to split a
     * property_amenities row back into unit_amenities rows.
     */
    public function down(): void
    {
    }
};
