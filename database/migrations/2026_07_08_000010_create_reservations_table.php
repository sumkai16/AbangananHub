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
            $table->unsignedBigInteger('unit_id')->nullable();
            $table->foreign('unit_id')->references('unit_id')->on('property_units')->onDelete('cascade');
            $table->unsignedBigInteger('tenant_id');
            $table->foreign('tenant_id')->references('user_id')->on('users')->onDelete('cascade');
            $table->unsignedBigInteger('conversation_id')->nullable();
            $table->foreign('conversation_id')->references('conversation_id')->on('conversations')->nullOnDelete();
            $table->date('reservation_date');
            $table->date('target_move_in_date')->nullable();
            $table->date('target_move_out_date')->nullable();
            $table->string('duration_of_stay')->nullable();
            $table->integer('occupants_count')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->string('rental_status')->default('Inquiry');
            $table->text('agreement_terms_notes')->nullable();
            $table->timestamp('agreed_at')->nullable();
            $table->string('agreed_ip')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};
