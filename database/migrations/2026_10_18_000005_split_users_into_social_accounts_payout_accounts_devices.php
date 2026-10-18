<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Moves three groups of columns out of `users`, each with its own lifecycle:
 *
 * - provider, provider_id      -> social_accounts       (many per user; no overwrite when a second provider links)
 * - gcash_number, gcash_account_name -> user_payout_accounts (1:1, landlords only; kept off every nested user payload)
 * - expo_push_token            -> user_devices          (many per user; a second phone no longer steals the first's pushes)
 *
 * down() copies the first social account and first device per user back, and the payout account as is.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_accounts', function (Blueprint $table) {
            $table->id('social_account_id');
            $table->foreignId('user_id')->constrained('users', 'user_id')->cascadeOnDelete();
            $table->string('provider', 30);
            $table->string('provider_id');
            $table->timestamps();

            $table->unique(['provider', 'provider_id']);
            $table->unique(['user_id', 'provider']);
        });

        Schema::create('user_payout_accounts', function (Blueprint $table) {
            $table->foreignId('user_id')->primary()->constrained('users', 'user_id')->cascadeOnDelete();
            $table->string('gcash_number')->nullable();
            $table->string('gcash_account_name')->nullable();
            $table->timestamps();
        });

        Schema::create('user_devices', function (Blueprint $table) {
            $table->id('device_id');
            $table->foreignId('user_id')->constrained('users', 'user_id')->cascadeOnDelete();
            $table->string('expo_push_token')->unique();
            $table->timestamps();
        });

        $now = now();

        DB::table('users')->whereNotNull('provider')->whereNotNull('provider_id')
            ->get(['user_id', 'provider', 'provider_id'])
            ->each(fn ($u) => DB::table('social_accounts')->insert([
                'user_id' => $u->user_id, 'provider' => $u->provider, 'provider_id' => $u->provider_id,
                'created_at' => $now, 'updated_at' => $now,
            ]));

        DB::table('users')->where(fn ($q) => $q->whereNotNull('gcash_number')->orWhereNotNull('gcash_account_name'))
            ->get(['user_id', 'gcash_number', 'gcash_account_name'])
            ->each(fn ($u) => DB::table('user_payout_accounts')->insert([
                'user_id' => $u->user_id, 'gcash_number' => $u->gcash_number, 'gcash_account_name' => $u->gcash_account_name,
                'created_at' => $now, 'updated_at' => $now,
            ]));

        DB::table('users')->whereNotNull('expo_push_token')
            ->get(['user_id', 'expo_push_token'])
            ->each(fn ($u) => DB::table('user_devices')->insert([
                'user_id' => $u->user_id, 'expo_push_token' => $u->expo_push_token,
                'created_at' => $now, 'updated_at' => $now,
            ]));

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['provider', 'provider_id']);
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['provider', 'provider_id', 'expo_push_token', 'gcash_number', 'gcash_account_name']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('provider')->nullable()->after('password');
            $table->string('provider_id')->nullable()->after('provider');
            $table->string('expo_push_token')->nullable()->after('provider_id');
            $table->string('gcash_number')->nullable()->after('contact_number');
            $table->string('gcash_account_name')->nullable()->after('gcash_number');
            $table->unique(['provider', 'provider_id']);
        });

        // First social account per user (lowest id), first device per user.
        DB::table('social_accounts')->orderBy('social_account_id')->get()->unique('user_id')
            ->each(fn ($a) => DB::table('users')->where('user_id', $a->user_id)
                ->update(['provider' => $a->provider, 'provider_id' => $a->provider_id]));

        DB::table('user_devices')->orderBy('device_id')->get()->unique('user_id')
            ->each(fn ($d) => DB::table('users')->where('user_id', $d->user_id)
                ->update(['expo_push_token' => $d->expo_push_token]));

        DB::table('user_payout_accounts')->get()
            ->each(fn ($p) => DB::table('users')->where('user_id', $p->user_id)
                ->update(['gcash_number' => $p->gcash_number, 'gcash_account_name' => $p->gcash_account_name]));

        Schema::dropIfExists('user_devices');
        Schema::dropIfExists('user_payout_accounts');
        Schema::dropIfExists('social_accounts');
    }
};
