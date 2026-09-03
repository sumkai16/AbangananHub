<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            // Nullable — most useful for the bedspace/boarding-house market;
            // an apartment or house listing may have nothing meaningful to
            // say here, so it isn't forced on every property.
            $table->enum('living_arrangement', [
                'Private', 'Shared', 'Mixed', 'Female only', 'Male only', 'Couples allowed', 'Family-friendly',
            ])->nullable()->after('property_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropColumn('living_arrangement');
        });
    }
};
