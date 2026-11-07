<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('landlord_verifications', function (Blueprint $table) {
            // Result of comparing the face on the ID with the live selfie (App\Services\FaceMatch). A signal
            // for the admin, never an automatic decision.
            $table->string('face_match_status', 20)->nullable()->after('ocr_status');
            $table->decimal('face_match_score', 5, 2)->nullable()->after('face_match_status');
        });
    }

    public function down(): void
    {
        Schema::table('landlord_verifications', function (Blueprint $table) {
            $table->dropColumn(['face_match_status', 'face_match_score']);
        });
    }
};
