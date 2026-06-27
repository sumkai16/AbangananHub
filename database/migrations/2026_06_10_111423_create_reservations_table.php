<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservations', function (Blueprint $table) {
            $table->id('reservation_id');
            $table->unsignedBigInteger('property_id');
            $table->foreign('property_id')->references('property_id')->on('properties')->onDelete('cascade');
            $table->unsignedBigInteger('tenant_id');
            $table->foreign('tenant_id')->references('user_id')->on('users')->onDelete('cascade');
            $table->date('reservation_date');
            $table->enum('reservation_status', ['Pending', 'Approved', 'Rejected', 'Cancelled'])->default('Pending');
            $table->text('remarks')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};