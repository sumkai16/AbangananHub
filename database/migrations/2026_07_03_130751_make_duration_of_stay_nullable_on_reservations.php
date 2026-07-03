<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->string('duration_of_stay')->nullable()->change();
            $table->integer('occupants_count')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->string('duration_of_stay')->nullable(false)->change();
            $table->integer('occupants_count')->nullable(false)->change();
        });
    }
};