<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Formal lease. See plans/formal-lease-agreement.md.
 *
 * lease_snapshot freezes the lease terms when the agreement is sent (online)
 * or the walk-in is saved, so editing the property later can't rewrite a
 * signed document. Existing rows stay null and render live, as before.
 *
 * The file columns hold the signed paper copy a landlord uploads for a
 * walk-in — private `local` disk, same as property_documents.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->json('lease_snapshot')->nullable()->after('agreement_terms_notes');
            $table->string('lease_file_path')->nullable()->after('lease_snapshot');
            $table->string('lease_file_name')->nullable()->after('lease_file_path');
            $table->timestamp('lease_uploaded_at')->nullable()->after('lease_file_name');
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn(['lease_snapshot', 'lease_file_path', 'lease_file_name', 'lease_uploaded_at']);
        });
    }
};
