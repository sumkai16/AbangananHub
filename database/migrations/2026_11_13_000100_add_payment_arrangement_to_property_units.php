<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How a unit is reserved: the landlord picks the payment arrangement per unit. 'advance_deposit' is what every
 * unit did before this column existed, so it is the default and nothing changes until a landlord opts in.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('property_units', function (Blueprint $table) {
            $table->string('payment_arrangement', 20)->default('advance_deposit')->after('security_deposit');
            $table->decimal('reservation_fee_amount', 10, 2)->nullable()->after('payment_arrangement');
            $table->unsignedTinyInteger('payment_deadline_days')->nullable()->after('reservation_fee_amount');
            $table->string('reservation_conditions', 500)->nullable()->after('payment_deadline_days');
        });
    }

    public function down(): void
    {
        Schema::table('property_units', function (Blueprint $table) {
            $table->dropColumn(['payment_arrangement', 'reservation_fee_amount', 'payment_deadline_days', 'reservation_conditions']);
        });
    }
};
