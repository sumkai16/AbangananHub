<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->timestamp('landlord_tc_accepted_at')->nullable()->after('agreed_ip');
            $table->timestamp('tenant_tc_accepted_at')->nullable()->after('landlord_tc_accepted_at');
            $table->timestamp('tenant_confirmed_move_in_at')->nullable()->after('tenant_tc_accepted_at');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->timestamp('released_at')->nullable()->after('paid_at');
            $table->unsignedBigInteger('released_by')->nullable()->after('released_at');
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn(['landlord_tc_accepted_at', 'tenant_tc_accepted_at', 'tenant_confirmed_move_in_at']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['released_at', 'released_by']);
        });
    }
};