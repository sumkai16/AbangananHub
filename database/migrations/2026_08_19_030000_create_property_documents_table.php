<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('property_documents', function (Blueprint $table) {
            $table->id('document_id');
            $table->unsignedBigInteger('property_id');
            $table->foreign('property_id')->references('property_id')->on('properties')->onDelete('cascade');
            $table->string('document_type', 100);
            // Nullable: null = admin-requested, awaiting the landlord's upload.
            $table->string('file_path')->nullable();
            $table->string('file_name')->nullable();
            $table->string('document_number', 100)->nullable();
            $table->enum('status', ['Pending', 'Verified', 'Rejected'])->default('Pending');
            $table->text('rejection_reason')->nullable();
            $table->date('expiry_date')->nullable();
            $table->unsignedBigInteger('verified_by')->nullable();
            $table->foreign('verified_by')->references('user_id')->on('users')->onDelete('set null');
            $table->timestamp('verified_at')->nullable();
            $table->unsignedBigInteger('requested_by')->nullable();
            $table->foreign('requested_by')->references('user_id')->on('users')->onDelete('set null');
            $table->timestamps();

            $table->index(['property_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('property_documents');
    }
};
