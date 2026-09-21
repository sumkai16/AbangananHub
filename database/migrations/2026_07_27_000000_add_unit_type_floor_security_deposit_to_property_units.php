<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * These three columns have been in PropertyUnit::$fillable and written by
     * both unit controllers since July 18, but nothing ever created them:
     * 2026_07_18_022220_add_unit_type_floor_deposit_description_to_property_units
     * is misnamed and only widens the availability_status enum. Unit creation
     * has been throwing SQLSTATE[42S22] on that path ever since.
     *
     * Sizes match the validation rules the controllers already enforce
     * (Landlord\PropertyUnitController + Api\Landlord\UnitWriteController):
     * string max:50, numeric max 999999.99.
     */
    public function up(): void
    {
        Schema::table('property_units', function (Blueprint $table) {
            $table->string('unit_type', 50)->nullable()->after('unit_label');
            $table->string('floor', 50)->nullable()->after('unit_type');
            $table->decimal('security_deposit', 8, 2)->nullable()->after('rental_fee');
        });
    }

    public function down(): void
    {
        Schema::table('property_units', function (Blueprint $table) {
            $table->dropColumn(['unit_type', 'floor', 'security_deposit']);
        });
    }
};
