<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Condominium becomes its own property type. Renters search for condos
 * separately from apartments (association dues, elevator, amenity deck), and
 * the Cebu IT Park / Lahug / Banilad listings were being labelled "Apartment".
 */
return new class extends Migration
{
    public function up(): void
    {
        // MySQL-only enum widening — see 2026_07_16_112509_update_payment_status_enum
        // for why this is guarded rather than run unconditionally.
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE properties MODIFY property_type ENUM('Bedspace','Room','Apartment','Condominium','House') NOT NULL");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            // Fold condos back into Apartment first so the narrower enum accepts every row.
            DB::table('properties')->where('property_type', 'Condominium')->update(['property_type' => 'Apartment']);
            DB::statement("ALTER TABLE properties MODIFY property_type ENUM('Bedspace','Room','Apartment','House') NOT NULL");
        }
    }
};
