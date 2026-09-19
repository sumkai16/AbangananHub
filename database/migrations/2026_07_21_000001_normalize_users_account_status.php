<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The `account_status` enum was declared as ['Active', 'Suspended'] (capitalized,
     * no "inactive" member), but the admin Users screens (create/edit/index/show) and
     * the primary UserController have used lowercase 'active'/'suspended'/'inactive'
     * since they were built. That mismatch meant:
     *   - suspending a user via Admin > Users never actually locked them out, because
     *     EnsureAccountActive only matched the capitalized 'Suspended' written by the
     *     Reports flow — not the lowercase value the Users screen was writing.
     *   - picking "Inactive" in the Users form wasn't a legal enum value at all.
     * This normalizes the column to the lowercase set the majority of the app already
     * assumes, and backfills existing rows to match. Uses raw SQL (not ->change())
     * since MySQL enum alteration needs doctrine/dbal, which isn't installed here.
     */
    public function up(): void
    {
        // MySQL enum matching is case-insensitive for the duplicate check, so
        // ('Active','active') in the same ENUM definition is rejected outright.
        // Widen through VARCHAR instead of trying to hold both casings in one ENUM.
        // MySQL-only raw ALTER — SQLite has no enum type to enforce and stores
        // this column as TEXT regardless, so the statement is a no-op there.
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY account_status VARCHAR(20) NOT NULL DEFAULT 'Active'");
        }

        DB::table('users')->where('account_status', 'Active')->update(['account_status' => 'active']);
        DB::table('users')->where('account_status', 'Suspended')->update(['account_status' => 'suspended']);

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY account_status ENUM('active', 'suspended', 'inactive') NOT NULL DEFAULT 'active'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY account_status VARCHAR(20) NOT NULL DEFAULT 'active'");
        }

        DB::table('users')->where('account_status', 'active')->update(['account_status' => 'Active']);
        DB::table('users')->where('account_status', 'suspended')->update(['account_status' => 'Suspended']);
        DB::table('users')->where('account_status', 'inactive')->update(['account_status' => 'Active']);

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY account_status ENUM('Active', 'Suspended') NOT NULL DEFAULT 'Active'");
        }
    }
};
