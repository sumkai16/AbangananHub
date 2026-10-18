<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Three composite indexes for queries that only had a foreign-key index.
 * Indexes only, no column changes. Each is guarded and explicitly named, like
 * 2026_09_21_000000_add_performance_indexes.
 */
return new class extends Migration
{
    private const INDEXES = [
        // "Is this unit spoken for": unit_id + whereNotIn(rental_status, TERMINAL_STATUSES),
        // run on every unit write and by the walk-in and occupancy checks.
        'reservations' => [
            'reservations_unit_status_index' => ['unit_id', 'rental_status'],
        ],
        // Conversation::messages() filters by thread and orders by sent_at.
        'messages' => [
            'messages_thread_order_index' => ['conversation_id', 'sent_at'],
        ],
        // Header badge and notifications page: user + is_read = false, newest first.
        // The existing (user_id, type, is_read) index cannot serve this: `type` sits in the middle.
        'notifications' => [
            'notifications_user_read_created_index' => ['user_id', 'is_read', 'created_at'],
        ],
    ];

    public function up(): void
    {
        foreach (self::INDEXES as $table => $indexes) {
            foreach ($indexes as $name => $columns) {
                if (! Schema::hasIndex($table, $name)) {
                    Schema::table($table, fn (Blueprint $t) => $t->index($columns, $name));
                }
                // The plain FK index down() adds back is redundant once the composite exists.
                $fkIndex = "{$table}_{$columns[0]}_index";
                if (Schema::hasIndex($table, $fkIndex)) {
                    Schema::table($table, fn (Blueprint $t) => $t->dropIndex($fkIndex));
                }
            }
        }
    }

    public function down(): void
    {
        foreach (self::INDEXES as $table => $indexes) {
            foreach ($indexes as $name => $columns) {
                // Each composite leads with a foreign-key column, and creating it let MySQL drop the
                // FK's own index (error 1553 on rollback). Give the FK a plain index back first.
                $fkIndex = "{$table}_{$columns[0]}_index";
                if (! Schema::hasIndex($table, $fkIndex)) {
                    Schema::table($table, fn (Blueprint $t) => $t->index($columns[0], $fkIndex));
                }
                if (Schema::hasIndex($table, $name)) {
                    Schema::table($table, fn (Blueprint $t) => $t->dropIndex($name));
                }
            }
        }
    }
};
