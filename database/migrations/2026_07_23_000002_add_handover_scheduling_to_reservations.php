<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The agreed key-handover slot.
 *
 * Clock 1 previously ran off target_move_in_date, which the tenant is required
 * to pick at inquiry time — before the landlord has even replied — and which
 * nothing can edit afterwards. By the time the pair have negotiated, signed and
 * paid, that guess can be weeks stale, yet it decides when the escrow escalates.
 *
 * A confirmed slot replaces it as the basis for the deadline. There is no column
 * for the cap's baseline: computeTurnoverDeadline() derives from
 * target_move_in_date and paid_at, neither of which ever changes, so the
 * original deadline stays recomputable and doesn't need a second home.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->timestamp('handover_at')->nullable()->after('move_in_last_reminder_on');
            $table->unsignedBigInteger('handover_proposed_by')->nullable()->after('handover_at');
            $table->timestamp('handover_proposed_at')->nullable()->after('handover_proposed_by');
            // Null while a slot is only proposed. Set means both parties agreed,
            // and only then does the slot move move_in_deadline_at.
            $table->timestamp('handover_confirmed_at')->nullable()->after('handover_proposed_at');

            $table->foreign('handover_proposed_by')
                ->references('user_id')->on('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropForeign(['handover_proposed_by']);
            $table->dropColumn([
                'handover_at',
                'handover_proposed_by',
                'handover_proposed_at',
                'handover_confirmed_at',
            ]);
        });
    }
};
