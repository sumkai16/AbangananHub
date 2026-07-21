<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('occupancy_activities', function (Blueprint $table) {
            $table->id('activity_id');
            $table->unsignedBigInteger('landlord_id');
            $table->unsignedBigInteger('property_id');
            $table->unsignedBigInteger('unit_id');
            $table->unsignedBigInteger('actor_id')->nullable();   // who triggered the change
            $table->unsignedBigInteger('tenant_id')->nullable();  // tenant involved, if any
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->timestamps();

            $table->foreign('landlord_id')->references('user_id')->on('users')->onDelete('cascade');
            $table->foreign('property_id')->references('property_id')->on('properties')->onDelete('cascade');
            $table->foreign('unit_id')->references('unit_id')->on('property_units')->onDelete('cascade');

            $table->index(['landlord_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('occupancy_activities');
    }
};
