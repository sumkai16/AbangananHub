<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * No landlord-facing form ever wrote this column — only PropertySeeder did,
     * so every real row is NULL and the tenant-facing "House rules" section
     * could never appear outside dev data. Rules are now carried by the
     * property_units policy booleans (pets_allowed, smoking_allowed,
     * visitors_allowed) and the "Rules & extras" amenity category, both of
     * which are collected by real forms and filterable.
     */
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropColumn('house_rules');
        });
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->json('house_rules')->nullable()->after('description');
        });
    }
};
