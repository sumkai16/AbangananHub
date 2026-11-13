<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The landlord's record of what became of move-in money (a reservation fee, say) the tenant paid directly when the
 * reservation ended. AbangananHub never holds that money, so nothing moves here: it is the landlord's own decision,
 * kept with a reason so the tenant can see it and, if they disagree, report it. Null decision = not settled yet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->string('move_in_refund_decision', 10)->nullable();
            $table->decimal('move_in_refund_amount', 10, 2)->nullable();
            $table->string('move_in_refund_note', 255)->nullable();
            $table->timestamp('move_in_refund_at')->nullable();
            $table->timestamp('move_in_refund_requested_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn(['move_in_refund_decision', 'move_in_refund_amount', 'move_in_refund_note', 'move_in_refund_at', 'move_in_refund_requested_at']);
        });
    }
};
