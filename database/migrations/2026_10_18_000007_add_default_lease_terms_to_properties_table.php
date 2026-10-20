<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A landlord's standard extra terms for a property (notarized-contract clauses, a visitor curfew, etc.).
     * Prefills the "Additional terms" box each time they send an agreement; the text actually agreed is
     * still frozen per reservation in reservations.agreement_terms_notes and the lease snapshot.
     */
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->text('default_lease_terms')->nullable()->after('house_rules');
        });
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropColumn('default_lease_terms');
        });
    }
};
