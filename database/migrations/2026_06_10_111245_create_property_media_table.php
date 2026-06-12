<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('property_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('properties')->onDelete('cascade');
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