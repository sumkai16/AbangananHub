<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id('review_id');
            $table->unsignedBigInteger('tenant_id');
            $table->foreign('tenant_id')->references('user_id')->on('users')->onDelete('cascade');
            $table->unsignedBigInteger('property_id');
            $table->foreign('property_id')->references('property_id')->on('properties')->onDelete('cascade');
            $table->unsignedBigInteger('landlord_id');
            $table->foreign('landlord_id')->references('user_id')->on('users')->onDelete('cascade');
            $table->unsignedTinyInteger('rating');
            $table->text('review_comment')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'property_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};