<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('property_units', function (Blueprint $table) {
            // bedrooms/bathrooms already exist as counts; bathroom_type and
            // kitchen_type answer a different question (private vs shared
            // access), and furnishing_status replaces the old two-state
            // is_furnished with the three states the analyst's filter list
            // asks for. All nullable — null means "not answered", not "no".
            $table->enum('bathroom_type', ['Private bathroom', 'Shared bathroom'])->nullable()->after('bathrooms');
            $table->enum('furnishing_status', ['Furnished', 'Semi-furnished', 'Unfurnished'])->nullable()->after('is_furnished');
            $table->enum('kitchen_type', ['Private kitchen', 'Shared kitchen', 'No kitchen'])->nullable()->after('furnishing_status');

            $table->boolean('pets_allowed')->nullable()->after('kitchen_type');
            $table->boolean('smoking_allowed')->nullable()->after('pets_allowed');
            $table->boolean('visitors_allowed')->nullable()->after('smoking_allowed');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('property_units', function (Blueprint $table) {
            $table->dropColumn([
                'bathroom_type',
                'furnishing_status',
                'kitchen_type',
                'pets_allowed',
                'smoking_allowed',
                'visitors_allowed',
            ]);
        });
    }
};
