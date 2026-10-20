<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * is_system and is_inquiry_summary answered one question ("what kind of message is this?") with
 * two booleans, which allowed a state that means nothing (both true). One ENUM replaces them.
 * A system line that narrates a concern is kind 'system' with concern_id set; 'concern' is the
 * tenant's own card. concern_id is unchanged.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('messages', 'kind')) {
            Schema::table('messages', function (Blueprint $t) {
                $t->enum('kind', ['user', 'system', 'inquiry_summary', 'concern'])->default('user')->after('message');
            });
        }

        // Order matters: the first match wins, so a flag beats concern_id.
        DB::table('messages')->where('is_system', true)->update(['kind' => 'system']);
        DB::table('messages')->where('kind', 'user')->where('is_inquiry_summary', true)->update(['kind' => 'inquiry_summary']);
        DB::table('messages')->where('kind', 'user')->whereNotNull('concern_id')->update(['kind' => 'concern']);

        Schema::table('messages', function (Blueprint $t) {
            $t->dropColumn(['is_system', 'is_inquiry_summary']);
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $t) {
            $t->boolean('is_system')->default(false)->after('message');
            $t->boolean('is_inquiry_summary')->default(false)->after('is_system');
        });

        DB::table('messages')->where('kind', 'system')->update(['is_system' => true]);
        DB::table('messages')->where('kind', 'inquiry_summary')->update(['is_inquiry_summary' => true]);

        Schema::table('messages', function (Blueprint $t) {
            $t->dropColumn('kind');
        });
    }
};
