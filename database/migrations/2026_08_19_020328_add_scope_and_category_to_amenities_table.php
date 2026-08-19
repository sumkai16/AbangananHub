<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('amenities', function (Blueprint $table) {
            $table->enum('scope', ['property', 'unit', 'both'])->default('both')->after('amenity_name');
            $table->string('category', 50)->nullable()->after('scope');
        });
    }

    public function down(): void
    {
        Schema::table('amenities', function (Blueprint $table) {
            $table->dropColumn(['scope', 'category']);
        });
    }
};
