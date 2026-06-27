<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->string('duration_of_stay')->after('reservation_date');
            $table->unsignedInteger('occupants_count')->after('duration_of_stay');
            
            // New booking state management tracking columns
            $table->enum('status', ['Pending', 'Approved', 'Rejected', 'Cancelled'])->default('Pending')->after('occupants_count');
            $table->text('rejection_reason')->nullable()->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn(['duration_of_stay', 'occupants_count', 'status', 'rejection_reason']);
        });
    }
};