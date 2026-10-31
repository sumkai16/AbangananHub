<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payouts', function (Blueprint $table) {
            // Processing = the PayMongo transfer is created and not confirmed yet. Every row that existed before
            // automatic payouts was an admin recording a GCash transfer already sent, hence the 'Sent' default.
            $table->enum('status', ['Processing', 'Sent', 'Failed'])->default('Sent')->after('amount');
            $table->string('paymongo_transfer_id')->nullable()->after('payout_reference');
            $table->string('failure_reason', 500)->nullable()->after('paymongo_transfer_id');
            // A Failed payout blocks the landlord's automatic payouts until an admin looks at it (Retry sets this).
            $table->timestamp('resolved_at')->nullable()->after('failure_reason');
            $table->index('status');
            $table->index('paymongo_transfer_id');
        });

        // Set only once the transfer is confirmed, so a Processing payout has none.
        Schema::table('payouts', function (Blueprint $table) {
            $table->timestamp('paid_out_at')->nullable()->change();
        });

        Schema::table('user_payout_accounts', function (Blueprint $table) {
            // Set for 48 hours whenever the GCash details change, so a hijacked account cannot redirect money at once.
            $table->timestamp('payouts_hold_until')->nullable()->after('accepts_online');
            // An admin's pause: no automatic payouts to this landlord until resumed.
            $table->timestamp('paused_at')->nullable()->after('payouts_hold_until');
            $table->unsignedBigInteger('paused_by')->nullable()->after('paused_at');
            $table->foreign('paused_by')->references('user_id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('user_payout_accounts', function (Blueprint $table) {
            $table->dropForeign(['paused_by']);
            $table->dropColumn(['payouts_hold_until', 'paused_at', 'paused_by']);
        });

        Schema::table('payouts', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['paymongo_transfer_id']);
            $table->dropColumn(['status', 'paymongo_transfer_id', 'failure_reason', 'resolved_at']);
        });
    }
};
