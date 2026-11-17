<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When the tenant last asked the landlord to share a payment QR (the "Ask Maria to share her QR" button on the
 * pay-directly card). Set means a request is open: the tenant sees "you asked" instead of the button for an hour,
 * and the landlord's Share payment QR button is highlighted. Cleared when the landlord shares a QR.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->timestamp('qr_requested_at')->nullable()->after('refund_report_id');
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn('qr_requested_at');
        });
    }
};
