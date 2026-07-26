<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * QRPh joins GCash as a second online method PayMongo's checkout page offers
 * tenants — same escrow rules, same webhook path, just another enum value.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE payments MODIFY payment_method ENUM('GCash','QRPh','Cash','Bank Transfer','Maya','Check','Other') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE payments MODIFY payment_method ENUM('GCash','Cash','Bank Transfer','Maya','Check','Other') NOT NULL");
    }
};
