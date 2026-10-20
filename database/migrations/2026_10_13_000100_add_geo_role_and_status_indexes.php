<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Three small indexes the audit found missing.
 *  - properties (latitude, longitude): AI search's "near X" box filter reads every row without it.
 *  - user_roles unique (user_id, role) + index (role, user_id): admin counts users by role and
 *    filters with whereHas('roles'); the unique key also stops a user holding a role twice.
 *  - users.account_status: the admin users page runs a count per status.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->index(['latitude', 'longitude'], 'properties_geo_index');
        });

        Schema::table('user_roles', function (Blueprint $table) {
            $table->unique(['user_id', 'role'], 'user_roles_user_role_unique');
            $table->index(['role', 'user_id'], 'user_roles_role_user_index');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->index('account_status', 'users_account_status_index');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_account_status_index');
        });

        Schema::table('user_roles', function (Blueprint $table) {
            $table->dropIndex('user_roles_role_user_index');
            $table->dropUnique('user_roles_user_role_unique');
        });

        Schema::table('properties', function (Blueprint $table) {
            $table->dropIndex('properties_geo_index');
        });
    }
};
