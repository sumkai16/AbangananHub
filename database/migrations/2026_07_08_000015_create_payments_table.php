<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id('payment_id');

            $table->unsignedBigInteger('reservation_id');
            $table->foreign('reservation_id')
                ->references('reservation_id')->on('reservations')
                ->onDelete('cascade');

            $table->enum('payment_type', ['Initial', 'Monthly']);
            $table->date('billing_period')->nullable();

            $table->decimal('amount', 10, 2);
            $table->enum('payment_method', ['GCash']);

            $table->string('paymongo_payment_intent_id')->nullable()->unique();
            $table->string('paymongo_payment_id')->nullable();
            $table->string('paymongo_checkout_session_id')->nullable()->unique();

            $table->enum('status', ['Pending', 'Paid', 'Failed', 'Refunded'])
                ->default('Pending');

            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
