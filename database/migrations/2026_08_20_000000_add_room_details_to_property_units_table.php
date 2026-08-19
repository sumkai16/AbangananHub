<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('property_units', function (Blueprint $table) {
            $table->unsignedTinyInteger('bedrooms')->nullable()->after('floor');
            $table->unsignedTinyInteger('bathrooms')->nullable()->after('bedrooms');
            $table->boolean('is_furnished')->nullable()->after('bathrooms');
        });
    }

    public function down(): void
    {
        Schema::table('property_units', function (Blueprint $table) {
            $table->dropColumn(['bedrooms', 'bathrooms', 'is_furnished']);
        });
    }
};
