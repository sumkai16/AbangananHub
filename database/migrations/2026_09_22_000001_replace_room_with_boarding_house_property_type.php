<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * "Room" is retired as a property type and "Boarding House" takes its place.
 * Every existing Room listing was a room in a shared building, which is exactly
 * what a boarding house is, so those rows are renamed rather than orphaned.
 * (A single room inside any property is still a *unit*; unit_type is free text.)
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
        DB::statement("ALTER TABLE properties MODIFY property_type ENUM('Bedspace','Room','Apartment','Condominium','House','Boarding House') NOT NULL");
        DB::table('properties')->where('property_type', 'Room')->update(['property_type' => 'Boarding House']);
        DB::statement("ALTER TABLE properties MODIFY property_type ENUM('Apartment','Condominium','House','Boarding House','Bedspace') NOT NULL");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE properties MODIFY property_type ENUM('Bedspace','Room','Apartment','Condominium','House','Boarding House') NOT NULL");
        DB::table('properties')->where('property_type', 'Boarding House')->update(['property_type' => 'Room']);
        DB::statement("ALTER TABLE properties MODIFY property_type ENUM('Bedspace','Room','Apartment','Condominium','House') NOT NULL");
    }
};