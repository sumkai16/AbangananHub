<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Three small indexes the audit found missing.
 *  - properties (latitude, longitude): AI search's "near X" box filter reads every row without it.
 *  - user_roles unique (user_id, role) + index (role, user_id): admin counts users by role and
 *    filters with whereHas('roles'); the unique key also stops a user holding a role twice.
 *  - users.account_status: the admin users page runs a count per status.
 *
 * Guarded like 2026_10_08_000000_add_browse_sort_indexes, so a re-run or a half-applied run is safe.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasIndex('properties', 'properties_geo_index')) {
            Schema::table('properties', fn (Blueprint $table) => $table->index(['latitude', 'longitude'], 'properties_geo_index'));
        }

        // The unique key cannot be built while a user holds the same role twice: keep the oldest row of each pair.
        $keep = DB::table('user_roles')->selectRaw('MIN(role_id) as id')->groupBy('user_id', 'role')->pluck('id');
        DB::table('user_roles')->whereNotIn('role_id', $keep)->delete();

        if (! Schema::hasIndex('user_roles', 'user_roles_user_role_unique')) {
            Schema::table('user_roles', fn (Blueprint $table) => $table->unique(['user_id', 'role'], 'user_roles_user_role_unique'));
        }

        if (! Schema::hasIndex('user_roles', 'user_roles_role_user_index')) {
            Schema::table('user_roles', fn (Blueprint $table) => $table->index(['role', 'user_id'], 'user_roles_role_user_index'));
        }

        if (! Schema::hasIndex('users', 'users_account_status_index')) {
            Schema::table('users', fn (Blueprint $table) => $table->index('account_status', 'users_account_status_index'));
        }
    }

    public function down(): void
    {
        if (Schema::hasIndex('users', 'users_account_status_index')) {
            Schema::table('users', fn (Blueprint $table) => $table->dropIndex('users_account_status_index'));
        }

        // The user_id foreign key now relies on the unique key (it starts with user_id). Give the key a
        // plain index first, or dropping the unique fails with error 1553.
        if (! Schema::hasIndex('user_roles', 'user_roles_user_id_index')) {
            Schema::table('user_roles', fn (Blueprint $table) => $table->index('user_id', 'user_roles_user_id_index'));
        }

        if (Schema::hasIndex('user_roles', 'user_roles_role_user_index')) {
            Schema::table('user_roles', fn (Blueprint $table) => $table->dropIndex('user_roles_role_user_index'));
        }

        if (Schema::hasIndex('user_roles', 'user_roles_user_role_unique')) {
            Schema::table('user_roles', fn (Blueprint $table) => $table->dropUnique('user_roles_user_role_unique'));
        }

        if (Schema::hasIndex('properties', 'properties_geo_index')) {
            Schema::table('properties', fn (Blueprint $table) => $table->dropIndex('properties_geo_index'));
        }
    }
};
