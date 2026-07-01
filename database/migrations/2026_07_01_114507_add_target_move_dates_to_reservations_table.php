<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->date('target_move_in_date')->nullable()->after('reservation_date');
            $table->date('target_move_out_date')->nullable()->after('target_move_in_date');
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn(['target_move_in_date', 'target_move_out_date']);
        });
    }
};