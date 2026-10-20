<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deposit_charges', function (Blueprint $table) {
            $table->id('deposit_charge_id');
            $table->foreignId('reservation_id')->constrained('reservations', 'reservation_id')->cascadeOnDelete();
            $table->decimal('amount', 10, 2);
            $table->enum('category', ['Damage', 'Cleaning', 'Missing Item', 'Unpaid Utility', 'Other']);
            $table->text('description');
            $table->date('charged_at');
            $table->foreignId('charged_by')->nullable()->constrained('users', 'user_id')->nullOnDelete();

            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users', 'user_id')->nullOnDelete();
            // Named causes, not free text — same role void_reason plays on
            // payments: the field a disputed charge is argued from later.
            $table->enum('void_reason', [
                'wrong_amount', 'wrong_tenancy', 'not_applicable', 'duplicate', 'other',
            ])->nullable();
            $table->string('void_note', 255)->nullable();

            $table->timestamps();

            $table->index(['reservation_id', 'voided_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deposit_charges');
    }
};
