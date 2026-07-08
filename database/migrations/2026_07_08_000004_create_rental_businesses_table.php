<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rental_businesses', function (Blueprint $table) {
            $table->id('business_id');
            $table->unsignedBigInteger('landlord_id')->unique();
            $table->foreign('landlord_id')->references('user_id')->on('users')->onDelete('cascade');
            $table->string('business_name');
            $table->text('description')->nullable();
            $table->string('logo_url')->nullable();
            $table->string('contact_number');
            $table->string('business_address');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rental_businesses');
    }
};
