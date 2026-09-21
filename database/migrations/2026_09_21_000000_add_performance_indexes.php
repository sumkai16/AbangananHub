<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Composite indexes for the hot read paths (browse, area tiles, header badges,
 * landlord analytics). Only foreign keys were indexed before, so every
 * visibility / availability / status filter scanned its whole table.
 *
 * Each is guarded so the migration is safe to re-run, and named explicitly so
 * `down()` doesn't depend on Laravel's generated names.
 */
return new class extends Migration
{
    private const INDEXES = [
        // Property::scopeLive() — the visibility gate on every tenant-facing query.
        'properties' => [
            'properties_visibility_index' => ['verification_status', 'publication_status'],
            'properties_city_index' => ['city_municipality'],
        ],
        // scopeBrowsable(): "has an available, approved unit" + MIN(rental_fee).
        // property_id leads and rental_fee trails, so the min-price subselect is
        // answered from the index alone.
        'property_units' => [
            'property_units_bookable_index' => ['property_id', 'availability_status', 'verification_status', 'rental_fee'],
        ],
        // Unread-message badge: conversation + unread + not-mine.
        'messages' => [
            'messages_unread_index' => ['conversation_id', 'is_read', 'sender_id'],
        ],
        // Landlord inquiries badge / analytics status counts; tenant reservation lists.
        'reservations' => [
            'reservations_property_status_index' => ['property_id', 'rental_status'],
            'reservations_tenant_status_index' => ['tenant_id', 'rental_status'],
        ],
        // Landlord analytics revenue windows.
        'payments' => [
            'payments_status_paid_at_index' => ['status', 'paid_at'],
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
