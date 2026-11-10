<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            // The photo the landlord attaches when marking the keys turned over. Evidence for the tenant and an
            // admin reviewing a dispute, not proof. Nullable: countdowns already running before this existed have none.
            $table->string('keys_handover_photo_url', 500)->nullable()->after('keys_turned_over_at');
            $table->string('keys_handover_photo_public_id', 255)->nullable()->after('keys_handover_photo_url');
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn(['keys_handover_photo_url', 'keys_handover_photo_public_id']);
        });
    }
};
