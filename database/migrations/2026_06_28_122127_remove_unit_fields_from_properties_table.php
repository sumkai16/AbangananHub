<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropColumn(['rental_fee', 'occupancy_limit', 'availability_status']);
        });
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->decimal('rental_fee', 10, 2)->after('longitude');
            $table->unsignedInteger('occupancy_limit')->after('rental_fee');
            $table->enum('availability_status', ['Available', 'Reserved', 'Occupied'])->default('Available')->after('occupancy_limit');
        });
    }
};