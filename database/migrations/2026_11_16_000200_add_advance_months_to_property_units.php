<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Months of advance rent a unit asks for at move-in: 0 (none), 1 or 2. Default 1 because every unit asked for one
 * month before this column existed (Reservation::agreementDefaults), so nothing changes until a landlord edits it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('property_units', function (Blueprint $table) {
            $table->unsignedTinyInteger('advance_months')->default(1)->after('security_deposit');
        });
    }

    public function down(): void
    {
        Schema::table('property_units', function (Blueprint $table) {
            $table->dropColumn('advance_months');
        });
    }
};
