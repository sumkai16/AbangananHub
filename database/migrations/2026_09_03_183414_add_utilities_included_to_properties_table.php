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
            // Nullable, not default(false): null means "landlord hasn't said"
            // rather than "confirmed not included" — same convention as
            // property_units.is_furnished (SCHEMA.md). The tenant-facing page
            // only renders an explicit "not included" callout once the
            // landlord has actually answered.
            $table->boolean('water_included')->nullable()->after('living_arrangement');
            $table->boolean('electricity_included')->nullable()->after('water_included');
            $table->boolean('internet_included')->nullable()->after('electricity_included');
            $table->boolean('association_fees_included')->nullable()->after('internet_included');
            $table->boolean('utilities_separately_metered')->nullable()->after('association_fees_included');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropColumn([
                'water_included',
                'electricity_included',
                'internet_included',
                'association_fees_included',
                'utilities_separately_metered',
            ]);
        });
    }
};
