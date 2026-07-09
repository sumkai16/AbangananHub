<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('landlord_verifications', function (Blueprint $table) {
            $table->string('id_type')->after('government_id');
            $table->string('selfie')->after('id_type');
            $table->string('id_image_hash', 64)->after('selfie');
            $table->string('id_number')->nullable()->after('id_image_hash');
            $table->string('ocr_name')->nullable()->after('id_number');
            $table->unsignedTinyInteger('ocr_confidence')->nullable()->after('ocr_name');
            $table->string('ocr_status')->nullable()->after('ocr_confidence');
        });
    }

    public function down(): void
    {
        Schema::table('landlord_verifications', function (Blueprint $table) {
            $table->dropColumn([
                'id_type',
                'selfie',
                'id_image_hash',
                'id_number',
                'ocr_name',
                'ocr_confidence',
                'ocr_status',
            ]);
        });
    }
};