<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Which direct ways a landlord lets tenants pay them (Settings > Payments). On `users`, not on
     * user_payout_accounts: that row only exists while GCash details are saved, and a landlord with no GCash
     * still chooses between QR and cash. Online stays `user_payout_accounts.accepts_online` (it needs the GCash).
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('accepts_qr_payments')->default(true)->after('profile_visibility');
            $table->boolean('accepts_cash_payments')->default(true)->after('accepts_qr_payments');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['accepts_qr_payments', 'accepts_cash_payments']);
        });
    }
};
