<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('landlord_verifications', function (Blueprint $table) {
            // Did the ID photos look like an ID (App\Services\IdDocumentCheck)? A signal for the admin, never a decision.
            // pass | unsure | fail | unavailable; null on applications made before the check existed.
            $table->string('id_check_status', 20)->nullable()->after('ocr_status');
            // Do the words on the card match the ID type the applicant chose: match | mismatch | unknown.
            $table->string('id_type_match', 20)->nullable()->after('id_check_status');
            // True when a photo failed the check and the applicant carried on after the allowed tries.
            $table->boolean('id_check_overridden')->default(false)->after('id_type_match');
        });
    }

    public function down(): void
    {
        Schema::table('landlord_verifications', function (Blueprint $table) {
            $table->dropColumn(['id_check_status', 'id_type_match', 'id_check_overridden']);
        });
    }
};
