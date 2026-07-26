<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A landlord's payout destination. Nullable — tenants share this table and
 * never need it, and a landlord simply can't be paid out until both are set.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('gcash_number')->nullable()->after('contact_number');
            $table->string('gcash_account_name')->nullable()->after('gcash_number');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['gcash_number', 'gcash_account_name']);
        });
    }
};
