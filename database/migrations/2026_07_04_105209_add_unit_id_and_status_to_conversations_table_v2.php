<?php

use Illuminate\Database\Migrations\Migration;

/**
 * NO-OP: unit_id and status columns are already added by
 * 2026_07_01_114510_add_unit_id_and_status_to_conversations_table.
 * This v2 migration was only needed to patch databases where the
 * original had been edited after running. On migrate:fresh the
 * original runs first, making this redundant.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Intentionally empty — see docblock above.
    }

    public function down(): void
    {
        // Intentionally empty.
    }
};