<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            // An admin refunded a held online payment back to the tenant. Written on every row of the checkout
            // group (rent and deposit are one payment on PayMongo's side), status = 'Refunded'.
            $table->timestamp('refunded_at')->nullable();
            $table->unsignedBigInteger('refunded_by')->nullable();
            $table->string('refund_reason', 255)->nullable();
            $table->string('paymongo_refund_id')->nullable()->index();
            // PayMongo's own word for where the refund is: pending, processing, succeeded or failed.
            $table->string('refund_status', 20)->nullable();
            // QR Ph refunds stay `processing` until the tenant claims money through this link (valid 3 days).
            $table->string('refund_transfer_link', 500)->nullable();
            // True when the admin also gave back the online payment fee.
            $table->boolean('refund_includes_fee')->default(false);

            $table->foreign('refunded_by')->references('user_id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['refunded_by']);
            $table->dropIndex(['paymongo_refund_id']);
            $table->dropColumn([
                'refunded_at', 'refunded_by', 'refund_reason', 'paymongo_refund_id',
                'refund_status', 'refund_transfer_link', 'refund_includes_fee',
            ]);
        });
    }
};
