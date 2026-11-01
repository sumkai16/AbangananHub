<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Documents a lease asks the tenant to upload before signing (plans/required-tenant-documents.md). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lease_templates', function (Blueprint $table) {
            // [{"key":"valid_id"},{"key":"other","label":"Letter from employer"}]; null = asks for nothing.
            $table->json('required_documents')->nullable();
        });

        Schema::create('tenancy_documents', function (Blueprint $table) {
            $table->id('tenancy_document_id');
            $table->foreignId('reservation_id')->constrained('reservations', 'reservation_id')->cascadeOnDelete();
            $table->string('doc_key', 40);
            $table->string('label', 100);
            $table->enum('status', ['Requested', 'Pending', 'Approved', 'Rejected', 'Waived'])->default('Requested');
            // Private disk. Null until uploaded, and again after the purge.
            $table->string('file_path')->nullable();
            $table->string('file_name')->nullable();
            $table->string('mime', 100)->nullable();
            $table->unsignedInteger('size')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('uploaded_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users', 'user_id')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            // Set when the reservation ends: today if it was never signed, else +30 days.
            $table->date('purge_after')->nullable();
            $table->timestamp('purged_at')->nullable();
            $table->timestamps();

            $table->unique(['reservation_id', 'doc_key']);
            $table->index('purge_after');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenancy_documents');
        Schema::table('lease_templates', fn (Blueprint $table) => $table->dropColumn('required_documents'));
    }
};
