<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->string('city_municipality', 100)->nullable()->after('address');
            $table->string('barangay', 100)->nullable()->after('city_municipality');
        });

        // Pre-existing fixture rows (escrow/walk-in scenario builders) were
        // seeded with a deliberately-fake Butuan City address, which predates
        // the Cebu-only rule this migration is part of. Repoint them to match
        // the now-fixed fixture builders so they parse cleanly below instead
        // of backfilling "Fixture Address" as a city.
        DB::table('properties')
            ->where('address', 'Fixture Address, Butuan City')
            ->update([
                'address'   => 'Fixture Address, Lahug, Cebu City, Cebu',
                'latitude'  => 10.3280,
                'longitude' => 123.8980,
            ]);

        // Backfill from the existing free-text address. Live rows are
        // "..., <LGU>, Cebu" — the LGU is the second-to-last comma segment,
        // not always the second one (some addresses carry an extra
        // sub-locality segment, e.g. "Cebu IT Park, Apas, Cebu City, Cebu").
        // Deliberately not defaulted to a single LGU: existing properties
        // span Cebu City, Mandaue, and Talisay — a blanket default would
        // mislabel most of them.
        foreach (DB::table('properties')->select('property_id', 'address')->get() as $property) {
            $parts = array_map('trim', explode(',', $property->address));
            $city = count($parts) >= 2 ? $parts[count($parts) - 2] : null;

            DB::table('properties')
                ->where('property_id', $property->property_id)
                ->update(['city_municipality' => $city]);
        }

        Schema::table('properties', function (Blueprint $table) {
            $table->string('city_municipality', 100)->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropColumn(['city_municipality', 'barangay']);
        });
    }
};
