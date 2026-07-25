<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Idempotency guard for the rent-reminder loop.
 *
 * The ledger's periods and due dates are derived, so the reminder command
 * could compute the same milestone every night. Pure date arithmetic isn't
 * enough — a missed run or a double run would gap or double-fire — so each
 * milestone that fires records one row here, and the unique index turns a
 * re-run into a no-op. Same reasoning the escrow loop used move_in_last_
 * reminder_on for, generalised to many periods per tenancy.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rent_reminders', function (Blueprint $table) {
            $table->id('reminder_id');
            $table->unsignedBigInteger('reservation_id');
            $table->foreign('reservation_id')->references('reservation_id')->on('reservations')->onDelete('cascade');

            // The billing month this reminder is about.
            $table->date('billing_period');

            // due_soon | due_today | overdue_w1 .. overdue_wN — one per period.
            $table->string('milestone', 20);

            $table->timestamp('created_at')->nullable();

            // The whole point: a milestone fires at most once per period.
            $table->unique(['reservation_id', 'billing_period', 'milestone'], 'rent_reminders_unique_milestone');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rent_reminders');
    }
};
