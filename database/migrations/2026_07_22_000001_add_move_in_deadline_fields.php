<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->timestamp('keys_turned_over_at')->nullable()->after('tenant_confirmed_move_in_at');
            $table->timestamp('move_in_deadline_at')->nullable()->after('keys_turned_over_at');
            $table->timestamp('move_in_disputed_at')->nullable()->after('move_in_deadline_at');
            $table->text('move_in_dispute_reason')->nullable()->after('move_in_disputed_at');
            $table->date('move_in_last_reminder_on')->nullable()->after('move_in_dispute_reason');

            // The nightly command scans on these two together.
            $table->index(['move_in_deadline_at', 'move_in_disputed_at'], 'reservations_move_in_deadline_index');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->enum('release_reason', ['tenant_confirmed', 'auto_expiry', 'admin_manual'])
                ->nullable()
                ->after('released_by');
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropIndex('reservations_move_in_deadline_index');
            $table->dropColumn([
                'keys_turned_over_at',
                'move_in_deadline_at',
                'move_in_disputed_at',
                'move_in_dispute_reason',
                'move_in_last_reminder_on',
            ]);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('release_reason');
        });
    }
};
