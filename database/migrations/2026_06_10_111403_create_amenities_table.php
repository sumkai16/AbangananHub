<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('amenities', function (Blueprint $table) {
            $table->id('amenity_id');
            $table->string('amenity_name')->unique();
            $table->timestamps();
        });

        Schema::create('property_amenities', function (Blueprint $table) {
            $table->unsignedBigInteger('property_id');
            $table->foreign('property_id')->references('property_id')->on('properties')->onDelete('cascade');
            $table->unsignedBigInteger('amenity_id');
            $table->foreign('amenity_id')->references('amenity_id')->on('amenities')->onDelete('cascade');
            $table->primary(['property_id', 'amenity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('property_amenities');
        Schema::dropIfExists('amenities');
    }
};