<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_payout_accounts', function (Blueprint $table) {
            // False = this landlord does not want tenants to pay them online; tenants then see only the direct route.
            $table->boolean('accepts_online')->default(true)->after('gcash_account_name');
        });
    }

    public function down(): void
    {
        Schema::table('user_payout_accounts', function (Blueprint $table) {
            $table->dropColumn('accepts_online');
        });
    }
};
