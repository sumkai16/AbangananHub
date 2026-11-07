<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // A landlord accepts the money-handling terms once per payment route, not on every agreement.
            $table->timestamp('landlord_terms_accepted_at')->nullable();
            $table->string('landlord_terms_payment_mode', 10)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['landlord_terms_accepted_at', 'landlord_terms_payment_mode']);
        });
    }
};
