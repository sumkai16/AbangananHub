<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            // What the tenant paid on top of the rent for paying online. Not owed to the landlord, so payouts
            // keep summing `amount` only. Held on the Initial / Monthly row of a checkout, 0 everywhere else.
            $table->decimal('service_fee', 10, 2)->default(0)->after('amount');

            // One online move-in checkout now funds several rows (rent + deposit), so they share the PayMongo ids.
            $table->dropUnique(['paymongo_checkout_session_id']);
            $table->dropUnique(['paymongo_payment_intent_id']);
            $table->index('paymongo_checkout_session_id');
            $table->index('paymongo_payment_intent_id');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['paymongo_checkout_session_id']);
            $table->dropIndex(['paymongo_payment_intent_id']);
            $table->unique('paymongo_checkout_session_id');
            $table->unique('paymongo_payment_intent_id');
            $table->dropColumn('service_fee');
        });
    }
};
