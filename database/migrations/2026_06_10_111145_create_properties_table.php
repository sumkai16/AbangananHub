<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('properties', function (Blueprint $table) {
            $table->id('property_id');
            $table->unsignedBigInteger('landlord_id');
            $table->foreign('landlord_id')->references('user_id')->on('users')->onDelete('cascade');
            $table->string('title');
            $table->text('description');
            $table->enum('property_type', ['Bedspace', 'Room', 'Apartment', 'House']);
            $table->string('address');
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->decimal('rental_fee', 10, 2);
            $table->unsignedInteger('occupancy_limit');
            $table->enum('availability_status', ['Available', 'Reserved', 'Occupied'])->default('Available');
            $table->enum('verification_status', ['Pending', 'Approved', 'Rejected'])->default('Pending');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('properties');
    }
};