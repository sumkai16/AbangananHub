<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Drops seven property_units columns nothing in the web app or API writes or reads any more.
 *
 * - unit_type, bathroom_type, kitchen_type, pets_allowed, smoking_allowed, visitors_allowed:
 *   removed from every form, API payload and display on Sept 23 2026. House rules are chosen once
 *   per property (properties.house_rules); bathroom and kitchen are expressed by unit amenities.
 * - is_furnished: the two-state twin of furnishing_status, which has been the field of record
 *   since Sept 2026.
 *
 * down() restores the columns. is_furnished is rebuilt from furnishing_status; the others come
 * back empty (their data is not recoverable, and 0 rows held values for the five that were
 * already unused).
 */
return new class extends Migration
{
    public function up(): void
    {
        $columns = array_values(array_filter([
            'unit_type', 'bathroom_type', 'kitchen_type',
            'pets_allowed', 'smoking_allowed', 'visitors_allowed', 'is_furnished',
        ], fn ($c) => Schema::hasColumn('property_units', $c)));

        if ($columns) {
            Schema::table('property_units', fn (Blueprint $t) => $t->dropColumn($columns));
        }
    }

    public function down(): void
    {
        Schema::table('property_units', function (Blueprint $t) {
            if (! Schema::hasColumn('property_units', 'unit_type')) {
                $t->string('unit_type', 50)->nullable()->after('unit_label');
            }
            if (! Schema::hasColumn('property_units', 'bathroom_type')) {
                $t->enum('bathroom_type', ['Private bathroom', 'Shared bathroom'])->nullable();
            }
            if (! Schema::hasColumn('property_units', 'kitchen_type')) {
                $t->enum('kitchen_type', ['Private kitchen', 'Shared kitchen', 'No kitchen'])->nullable();
            }
            foreach (['pets_allowed', 'smoking_allowed', 'visitors_allowed', 'is_furnished'] as $flag) {
                if (! Schema::hasColumn('property_units', $flag)) {
                    $t->boolean($flag)->nullable();
                }
            }
        });

        DB::table('property_units')->whereNotNull('furnishing_status')->update([
            'is_furnished' => DB::raw("furnishing_status <> 'Unfurnished'"),
        ]);
    }
};
