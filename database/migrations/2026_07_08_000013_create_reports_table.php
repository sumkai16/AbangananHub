<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->id('report_id');
            $table->unsignedBigInteger('reporter_id');
            $table->foreign('reporter_id')->references('user_id')->on('users')->onDelete('cascade');
            $table->unsignedBigInteger('property_id')->nullable();
            $table->foreign('property_id')->references('property_id')->on('properties')->onDelete('cascade');
            $table->unsignedBigInteger('reported_user_id')->nullable();
            $table->foreign('reported_user_id')->references('user_id')->on('users')->onDelete('cascade');
            $table->text('report_reason');
            $table->enum('report_status', ['Pending', 'Resolved'])->default('Pending');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
