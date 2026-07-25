<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Walk-in tenants: people who arranged the rental offline and were entered by
 * their landlord. They are real `users` rows so `reservations.tenant_id` can
 * stay NOT NULL and every view reading `$reservation->tenant->...` keeps
 * working — but they carry no usable password and an 'inactive' status, so the
 * row can never be logged into.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_walk_in')->default(false)->after('account_status');
            $table->unsignedBigInteger('created_by_landlord_id')->nullable()->after('is_walk_in');
            $table->foreign('created_by_landlord_id')
                ->references('user_id')->on('users')->nullOnDelete();
        });

        // A walk-in may have no email at all — a phone number is often the only
        // contact detail a landlord has. Raw ALTER rather than ->change() so
        // this doesn't need doctrine/dbal, matching update_payment_status_enum.
        // MySQL allows any number of NULLs under a UNIQUE index, so the
        // existing uniqueness guarantee on real addresses is unaffected.
        DB::statement('ALTER TABLE users MODIFY email VARCHAR(255) NULL');
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['created_by_landlord_id']);
            $table->dropColumn(['is_walk_in', 'created_by_landlord_id']);
        });

        // Only reversible while no null-email rows remain.
        DB::statement('ALTER TABLE users MODIFY email VARCHAR(255) NOT NULL');
    }
};
