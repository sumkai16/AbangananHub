<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('landlord_verifications', function (Blueprint $table) {
            // The ID type the front photo looked like (App\Services\IdTypeDetector), when one clearly led. Next to
            // id_type (what the applicant picked), so the admin sees "picked Passport, looks like a Postal ID".
            // Null when nothing clearly led, and on applications made before detection existed.
            $table->string('id_type_detected', 40)->nullable()->after('id_type_match');
        });
    }

    public function down(): void
    {
        Schema::table('landlord_verifications', function (Blueprint $table) {
            $table->dropColumn('id_type_detected');
        });
    }
};
