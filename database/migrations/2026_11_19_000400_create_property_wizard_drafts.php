<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Step 1 of the property wizard (Info) used to live only in the session, so closing the browser for a
        // while lost it. One row per landlord holds it until Location creates the real Draft property.
        Schema::create('property_wizard_drafts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('landlord_id')->unique();
            $table->json('payload');
            $table->timestamps();
        });

        // How many units the landlord said the property has; the wizard's Units and Review steps read it.
        // Was a session value, so it vanished with the session.
        Schema::table('properties', function (Blueprint $table) {
            $table->unsignedSmallInteger('target_units')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropColumn('target_units');
        });
        Schema::dropIfExists('property_wizard_drafts');
    }
};
