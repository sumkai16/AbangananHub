<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL-only enum widening — see 2026_07_16_112509_update_payment_status_enum
        // for why this is guarded rather than run unconditionally.
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE property_units MODIFY COLUMN availability_status ENUM('Available', 'Reserved', 'Occupied', 'Maintenance') DEFAULT 'Available'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE property_units MODIFY COLUMN availability_status ENUM('Available', 'Reserved', 'Occupied') DEFAULT 'Available'");
        }
    }
};