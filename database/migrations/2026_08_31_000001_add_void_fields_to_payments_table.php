<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Raw ALTER: MySQL enums can't be widened through the Blueprint. Same
        // approach as 2026_07_16_112509_update_payment_status_enum and
        // 2026_07_24_000003_add_manual_recording_to_payments_table.
        DB::statement("ALTER TABLE payments MODIFY COLUMN status ENUM('Pending', 'Paid', 'Held', 'Released', 'Failed', 'Refunded', 'Voided') NOT NULL DEFAULT 'Pending'");

        Schema::table('payments', function (Blueprint $table) {
            $table->timestamp('voided_at')->nullable()->after('payout_reference');
            $table->unsignedBigInteger('voided_by')->nullable()->after('voided_at');
            $table->foreign('voided_by')->references('user_id')->on('users')->nullOnDelete();

            // Named causes, not free text — same role release_reason plays for
            // releases: this is the field a disputed ledger is argued from.
            $table->enum('void_reason', [
                'wrong_amount', 'wrong_month', 'wrong_tenancy',
                'duplicate', 'not_received', 'other',
            ])->nullable()->after('voided_by');
            $table->string('void_note', 255)->nullable()->after('void_reason');

            // The correction that replaced a voided row. On the NEW row, not
            // the voided one: one void can be replaced by several rows when
            // RentPaymentAllocator splits the corrected amount across months.
            $table->unsignedBigInteger('replaces_payment_id')->nullable()->after('void_note');
            $table->foreign('replaces_payment_id')->references('payment_id')->on('payments')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['replaces_payment_id']);
            $table->dropForeign(['voided_by']);
            $table->dropColumn(['voided_at', 'voided_by', 'void_reason', 'void_note', 'replaces_payment_id']);
        });

        // Restore voided rows to Paid before narrowing the enum — MySQL would
        // otherwise blank or reject them. Dev rollback only; it resurrects
        // money rows, which is the correct behaviour for undoing this migration.
        DB::statement("UPDATE payments SET status = 'Paid' WHERE status = 'Voided'");
        DB::statement("ALTER TABLE payments MODIFY COLUMN status ENUM('Pending', 'Paid', 'Held', 'Released', 'Failed', 'Refunded') NOT NULL DEFAULT 'Pending'");
    }
};
