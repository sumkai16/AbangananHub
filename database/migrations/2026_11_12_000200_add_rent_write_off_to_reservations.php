<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "Stop collecting": the landlord stops chasing rent an ended tenant still owes. The months stay on the ledger,
     * but RentLedger::summary() reports no balance and no reminders go out. Reversible (both columns back to null).
     * The amount is what was outstanding at that moment, kept for the record.
     */
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->timestamp('rent_written_off_at')->nullable();
            $table->decimal('rent_written_off_amount', 10, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn(['rent_written_off_at', 'rent_written_off_amount']);
        });
    }
};
