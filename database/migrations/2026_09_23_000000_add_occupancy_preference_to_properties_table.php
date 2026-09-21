<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Who a property is for is now three property-level answers — living arrangement
 * (Private / Shared / Mixed), occupancy preference (No Preference / Men Only /
 * Women Only) and house rules — instead of a mixed bag in one enum.
 *
 * `living_arrangement` used to also carry the gender answers ('Female only',
 * 'Male only') plus 'Couples allowed' / 'Family-friendly'. The gender ones move
 * to the new `occupancy_preference` column; the other two have no equivalent
 * and go back to "not answered" (NULL), which the column already allows.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('properties', 'occupancy_preference')) {
            Schema::table('properties', function ($table) {
                $table->enum('occupancy_preference', ['No Preference', 'Men Only', 'Women Only'])
                    ->default('No Preference')
                    ->after('living_arrangement');
            });
        }

        DB::table('properties')->where('living_arrangement', 'Female only')->update(['occupancy_preference' => 'Women Only']);
        DB::table('properties')->where('living_arrangement', 'Male only')->update(['occupancy_preference' => 'Men Only']);
        DB::table('properties')
            ->whereIn('living_arrangement', ['Female only', 'Male only', 'Couples allowed', 'Family-friendly'])
            ->update(['living_arrangement' => null]);

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE properties MODIFY COLUMN living_arrangement ENUM('Private','Shared','Mixed') NULL");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE properties MODIFY COLUMN living_arrangement ENUM('Private','Shared','Mixed','Female only','Male only','Couples allowed','Family-friendly') NULL");
        }

        // Best-effort restore: a gender preference on a property with no arrangement goes back where it came from.
        DB::table('properties')->whereNull('living_arrangement')->where('occupancy_preference', 'Women Only')->update(['living_arrangement' => 'Female only']);
        DB::table('properties')->whereNull('living_arrangement')->where('occupancy_preference', 'Men Only')->update(['living_arrangement' => 'Male only']);

        if (Schema::hasColumn('properties', 'occupancy_preference')) {
            Schema::table('properties', function ($table) {
                $table->dropColumn('occupancy_preference');
            });
        }
    }
};
