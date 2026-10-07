<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Indexes for the browse page's sort and type filter. Sorting by price or
 * rating orders by correlated subselects over `property_units` and `reviews`;
 * the reviews one (AVG rating / COUNT, hidden rows excluded) had only the
 * property_id foreign key to lean on. Newest-first and the type chips had no
 * index at all.
 *
 * Guarded and explicitly named, same as 2026_09_21_000000_add_performance_indexes.
 */
return new class extends Migration
{
    private const INDEXES = [
        'properties' => [
            'properties_created_at_index' => ['created_at'],
            'properties_type_index' => ['property_type'],
        ],
        'reviews' => [
            'reviews_property_rating_index' => ['property_id', 'is_hidden', 'rating'],
        ],
    ];

    public function up(): void
    {
        foreach (self::INDEXES as $table => $indexes) {
            foreach ($indexes as $name => $columns) {
                if (! Schema::hasIndex($table, $name)) {
                    Schema::table($table, fn (Blueprint $t) => $t->index($columns, $name));
                }
            }
        }
    }

    public function down(): void
    {
        foreach (self::INDEXES as $table => $indexes) {
            foreach (array_keys($indexes) as $name) {
                if (Schema::hasIndex($table, $name)) {
                    Schema::table($table, fn (Blueprint $t) => $t->dropIndex($name));
                }
            }
        }
    }
};
