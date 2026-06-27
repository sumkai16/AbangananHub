<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('property_media', function (Blueprint $table) {
            $table->id('media_id');
            $table->unsignedBigInteger('property_id');
            $table->foreign('property_id')->references('property_id')->on('properties')->onDelete('cascade');
            $table->enum('media_type', ['Image', 'Video']);
            $table->string('media_url');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('property_media');
    }
};