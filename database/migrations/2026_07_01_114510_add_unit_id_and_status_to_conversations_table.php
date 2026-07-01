<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // No-op: unit_id and status already existed on conversations
        // prior to this migration (added outside tracked migration history).
        // Confirmed matching shape: unit_id (FK, nullable), status enum('Open','Resolved') default 'Open'.
    }

    public function down(): void
    {
        // Intentionally left as no-op — see up().
    }
};