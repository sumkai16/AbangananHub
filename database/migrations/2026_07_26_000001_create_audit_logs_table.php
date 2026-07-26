<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id('log_id');

            // Deliberately NOT cascade: deleting a user must not erase the record
            // of what they did. The denormalized name/email keep the row readable
            // once the FK is nulled.
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('actor_name');
            $table->string('actor_email');

            $table->string('action', 60);                    // 'reservation.force_cancel'

            // Polymorphic target — actions span reservations, users, payments,
            // properties, units, verifications, reviews and reports.
            $table->string('auditable_type')->nullable();
            $table->unsignedBigInteger('auditable_id')->nullable();

            $table->string('summary');                       // human sentence, rendered verbatim
            $table->text('reason')->nullable();              // rejection reason / admin note
            $table->json('metadata')->nullable();            // before/after values, amounts
            $table->string('ip_address', 45)->nullable();    // 45 chars fits IPv6

            // Append-only: no updated_at. Rows are never edited.
            $table->timestamp('created_at')->nullable();

            $table->foreign('actor_id')->references('user_id')->on('users')->onDelete('set null');

            $table->index('created_at');
            $table->index(['action', 'created_at']);
            $table->index(['auditable_type', 'auditable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
