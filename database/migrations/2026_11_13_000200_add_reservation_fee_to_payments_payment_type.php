<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * 'Reservation Fee' joins the payment categories so the fee that holds a unit is recorded apart from rent,
 * deposit and other charges. Advance rent is still 'Monthly' rows (see MoveInPaymentBreakdown), never its own type.
 */
return new class extends Migration
{
    public function up(): void
    {
        // MySQL-only enum widening, guarded like 2026_07_26_000000_add_qrph_to_payments_payment_method.
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE payments MODIFY payment_type ENUM('Initial','Monthly','Deposit','Utility','Other','Reservation Fee') NOT NULL");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE payments MODIFY payment_type ENUM('Initial','Monthly','Deposit','Utility','Other') NOT NULL");
        }
    }
};
