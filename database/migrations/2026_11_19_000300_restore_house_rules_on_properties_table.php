<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Puts `properties.house_rules` back on a database that lost it.
     *
     * 2026_09_12_000000_drop_house_rules_from_properties_table removed the column. That file was deleted in the
     * Oct 18 schema cleanup and the column was written back into 2026_07_08_000005_create_properties_table, but a
     * database that had already run the drop never got it back, while the code (PropertyPolicies, the wizard, the edit
     * page, search) reads and writes it again. On such a database adding a property failed with
     * "Unknown column 'house_rules'". A database built from the current migrations already has the column, so this
     * does nothing there.
     *
     * The rules that were dropped are gone (the old per-unit rules were never copied back); existing rows read as no
     * rules, which every reader treats as "no restriction".
     */
    public function up(): void
    {
        if (Schema::hasColumn('properties', 'house_rules')) {
            return;
        }

        Schema::table('properties', function (Blueprint $table) {
            $column = $table->json('house_rules')->nullable();

            if (Schema::hasColumn('properties', 'occupancy_preference')) {
                $column->after('occupancy_preference');
            }
        });
    }

    /** Not undone: on a database built from the current migrations this column is part of the table, not ours to drop. */
    public function down(): void
    {
    }
};
