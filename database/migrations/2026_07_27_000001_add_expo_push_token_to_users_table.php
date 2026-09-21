<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One token per user, same as provider/provider_id not getting a pivot
     * table — a device re-registering just overwrites the old token, and a
     * stale token from an uninstalled app fails Expo's send silently rather
     * than needing cleanup here.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('expo_push_token')->nullable()->after('provider_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('expo_push_token');
        });
    }
};
