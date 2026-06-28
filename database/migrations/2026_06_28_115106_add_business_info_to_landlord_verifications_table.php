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
        Schema::table('landlord_verifications', function (Blueprint $table) {
            $table->string('business_name')->nullable()->after('government_id');
            $table->text('description')->nullable()->after('business_name');
            $table->string('logo_url')->nullable()->after('description');
            $table->string('contact_number')->nullable()->after('logo_url');
            $table->string('business_address')->nullable()->after('contact_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('landlord_verifications', function (Blueprint $table) {
            $table->dropColumn([
                'business_name',
                'description',
                'logo_url',
                'contact_number',
                'business_address',
            ]);
        });
    }
};