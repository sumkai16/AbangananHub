<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unit_media', function (Blueprint $table) {
            $table->id('media_id');
            $table->unsignedBigInteger('unit_id');
            $table->foreign('unit_id')->references('unit_id')->on('property_units')->onDelete('cascade');
            $table->enum('media_type', ['Image', 'Video']);
            $table->string('media_url');
            $table->enum('source', ['camera', 'upload'])->default('upload');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unit_media');
    }
};