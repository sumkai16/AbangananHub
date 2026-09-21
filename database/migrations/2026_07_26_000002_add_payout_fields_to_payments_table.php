<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tracks whether money the platform owes a landlord (a Released initial
 * payment, or Paid monthly rent) has actually been sent to them yet. Manual
 * transfer, not a real disbursement API — see
 * docs/specs/2026-07-26-landlord-payout-design.md. Null means "not payout
 * eligible" (Pending/Held/Failed, or landlord-recorded offline rent, which
 * never needs a platform payout at all) — most rows never get a value here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('payout_status')->nullable()->after('release_reason');
            $table->timestamp('paid_out_at')->nullable()->after('payout_status');
            $table->unsignedBigInteger('paid_out_by')->nullable()->after('paid_out_at');
            $table->foreign('paid_out_by')->references('user_id')->on('users')->nullOnDelete();
            $table->string('payout_reference')->nullable()->after('paid_out_by');

            $table->index('payout_status');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['payout_status']);
            $table->dropForeign(['paid_out_by']);
            $table->dropColumn(['payout_status', 'paid_out_at', 'paid_out_by', 'payout_reference']);
        });
    }
};
