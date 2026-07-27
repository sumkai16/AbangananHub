<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `business_name`, `contact_number` and `business_address` were created
     * NOT NULL with no default, but Landlord\ProfileController@update (and
     * its API mirror) validate all three as `nullable` and only write the
     * fields that were actually submitted. A blank text input reaches the
     * controller as an empty string, which ConvertEmptyStringsToNull turns
     * into null — so a landlord's *first* profile save crashes with
     * SQLSTATE[HY000] 1364 the moment any one of the three is left blank.
     * Same shape as the property_units bug: validation promised optional,
     * the schema never agreed. Found via the mobile API's landlord profile
     * probe, but it was reachable from the web form too.
     */
    public function up(): void
    {
        Schema::table('rental_businesses', function (Blueprint $table) {
            $table->string('business_name')->nullable()->change();
            $table->string('contact_number')->nullable()->change();
            $table->string('business_address')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('rental_businesses', function (Blueprint $table) {
            $table->string('business_name')->nullable(false)->change();
            $table->string('contact_number')->nullable(false)->change();
            $table->string('business_address')->nullable(false)->change();
        });
    }
};
