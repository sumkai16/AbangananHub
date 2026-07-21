<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('occupancy_snapshots', function (Blueprint $table) {
            $table->id('snapshot_id');
            $table->unsignedBigInteger('landlord_id');
            $table->foreign('landlord_id')->references('user_id')->on('users')->onDelete('cascade');
            $table->date('snapshot_date');
            $table->unsignedInteger('total_units')->default(0);
            $table->unsignedInteger('available_units')->default(0);
            $table->unsignedInteger('reserved_units')->default(0);
            $table->unsignedInteger('occupied_units')->default(0);
            $table->unsignedInteger('maintenance_units')->default(0);
            $table->decimal('occupancy_rate', 5, 2)->default(0);
            $table->timestamps();

            $table->unique(['landlord_id', 'snapshot_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('occupancy_snapshots');
    }
};
