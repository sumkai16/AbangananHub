<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Condominium is shelved for now and Dormitory takes its place as a property
 * type. Existing Condominium rows are renamed rather than orphaned. Reversing
 * this restores the enum and turns Dormitory rows back into Condominium.
 */
return new class extends Migration
{
    public function up(): void
    {
        // MySQL-only enum change — see 2026_07_16_112509_update_payment_status_enum
        // for why this is guarded rather than run unconditionally.
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        // Widen first so the rename below is a legal value, then narrow.
        DB::statement("ALTER TABLE properties MODIFY property_type ENUM('Apartment','Condominium','House','Boarding House','Bedspace','Dormitory') NOT NULL");
        DB::table('properties')->where('property_type', 'Condominium')->update(['property_type' => 'Dormitory']);
        DB::statement("ALTER TABLE properties MODIFY property_type ENUM('Apartment','Dormitory','House','Boarding House','Bedspace') NOT NULL");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE properties MODIFY property_type ENUM('Apartment','Condominium','House','Boarding House','Bedspace','Dormitory') NOT NULL");
        DB::table('properties')->where('property_type', 'Dormitory')->update(['property_type' => 'Condominium']);
        DB::statement("ALTER TABLE properties MODIFY property_type ENUM('Apartment','Condominium','House','Boarding House','Bedspace') NOT NULL");
    }
};
