<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Orthogonal to verification_status, not a replacement for it:
        // verification_status answers "is this legitimate", publication_status
        // answers "should it be live right now". Every existing row defaults
        // to Published — publication was never a concept before this column,
        // so nothing was ever deliberately unpublished.
        //
        // One gap this backfill does NOT attempt to close: properties delisted
        // via Admin\ReportController's "delist_property" action are currently
        // recorded as verification_status = 'Rejected', indistinguishable in
        // the data from a listing that simply failed initial review. Those
        // could in principle be recovered from AuditLog ('report.resolve'
        // entries) in a real production database, but the dev DB has none —
        // noted here rather than silently backfilling them as Published.
        Schema::table('properties', function (Blueprint $table) {
            $table->enum('publication_status', ['Draft', 'Published', 'Unpublished', 'Suspended'])
                ->default('Published')
                ->after('verification_status');
        });
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropColumn('publication_status');
        });
    }
};
