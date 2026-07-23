<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Records whether the applicant completed the on-device liveness check.
     * False means they fell back to a plain camera capture (face-api.js
     * unavailable), so admin review has to confirm the face manually.
     */
    public function up(): void
    {
        Schema::table('landlord_verifications', function (Blueprint $table) {
            $table->boolean('liveness_passed')->default(false)->after('selfie');
        });
    }

    public function down(): void
    {
        Schema::table('landlord_verifications', function (Blueprint $table) {
            $table->dropColumn('liveness_passed');
        });
    }
};
