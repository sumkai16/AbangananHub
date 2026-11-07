<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** How many "still waiting for you" reminders the landlord has had for a payment report (payment-claims:remind). */
    public function up(): void
    {
        Schema::table('payment_claims', function (Blueprint $table) {
            $table->unsignedTinyInteger('reminders_sent')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('payment_claims', function (Blueprint $table) {
            $table->dropColumn('reminders_sent');
        });
    }
};
