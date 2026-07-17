<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('property_units', function (Blueprint $table) {
            $table->text('description')->nullable()->after('unit_label');
        });

        Schema::create('unit_amenities', function (Blueprint $table) {
            $table->unsignedBigInteger('unit_id');
            $table->unsignedBigInteger('amenity_id');
            $table->primary(['unit_id', 'amenity_id']);
            $table->foreign('unit_id')->references('unit_id')->on('property_units')->onDelete('cascade');
            $table->foreign('amenity_id')->references('amenity_id')->on('amenities')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unit_amenities');

        Schema::table('property_units', function (Blueprint $table) {
            $table->dropColumn('description');
        });
    }
};