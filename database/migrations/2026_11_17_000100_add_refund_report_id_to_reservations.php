<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The report a tenant sent to AbangananHub when their landlord kept money paid directly (a "Refund not returned"
 * report). One per reservation: a filled column is also the "already sent" mark. Same shape as
 * payment_claims.dispute_report_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->foreignId('refund_report_id')->nullable()->after('move_in_refund_requested_at')
                ->constrained('reports', 'report_id')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('refund_report_id');
        });
    }
};
