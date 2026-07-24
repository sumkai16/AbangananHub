<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Payments a landlord collected offline and is recording after the fact.
 *
 * The escrow path (Pending -> Held -> Released, PayMongo GCash) is untouched.
 * This widens the two enums it had narrowed to a single online method, and
 * adds the audit trail that keeps a landlord's assertion distinguishable from
 * a PayMongo settlement — the same role `release_reason` plays for releases.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE payments MODIFY payment_method ENUM('GCash','Cash','Bank Transfer','Maya','Check','Other') NOT NULL");
        DB::statement("ALTER TABLE payments MODIFY payment_type ENUM('Initial','Monthly','Deposit','Utility','Other') NOT NULL");

        Schema::table('payments', function (Blueprint $table) {
            // Null = settled by the platform through PayMongo. Non-null = a
            // landlord typed it in. Nothing else can tell the two apart.
            $table->unsignedBigInteger('recorded_by')->nullable()->after('release_reason');
            $table->foreign('recorded_by')->references('user_id')->on('users')->nullOnDelete();

            $table->string('reference_no')->nullable()->after('recorded_by');
            $table->text('payment_notes')->nullable()->after('reference_no');

            // The ledger looks up "payments for this reservation in this
            // billing month" once per rendered period.
            $table->index(['reservation_id', 'billing_period'], 'payments_reservation_period_index');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex('payments_reservation_period_index');
            $table->dropForeign(['recorded_by']);
            $table->dropColumn(['recorded_by', 'reference_no', 'payment_notes']);
        });

        DB::statement("ALTER TABLE payments MODIFY payment_method ENUM('GCash') NOT NULL");
        DB::statement("ALTER TABLE payments MODIFY payment_type ENUM('Initial','Monthly') NOT NULL");
    }
};
