<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('property_media', function (Blueprint $table) {
            $table->unsignedBigInteger('unit_id')->nullable()->after('property_id');
            $table->foreign('unit_id')->references('unit_id')->on('property_units')->onDelete('cascade');
            $table->string('cloudinary_public_id')->nullable()->after('media_url');
        });

        Schema::table('property_units', function (Blueprint $table) {
            $table->enum('verification_status', ['Pending', 'Approved', 'Rejected'])
                  ->default('Pending')
                  ->after('availability_status');
        });
    }

    public function down(): void
    {
        Schema::table('property_media', function (Blueprint $table) {
            $table->dropForeign(['unit_id']);
            $table->dropColumn(['unit_id', 'cloudinary_public_id']);
        });

        Schema::table('property_units', function (Blueprint $table) {
            $table->dropColumn('verification_status');
        });
    }
};