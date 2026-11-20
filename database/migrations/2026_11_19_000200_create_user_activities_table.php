<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_activities', function (Blueprint $table) {
            $table->id('activity_id');

            // Nulled (not cascaded) when a user is deleted, so the trail survives. Name, email and role are
            // snapshots: the row must stay readable after the account is gone or its role changes.
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('actor_name')->nullable();
            $table->string('actor_email')->nullable();
            $table->string('actor_role', 20)->nullable();   // Admin, Landlord, Tenant (highest held)

            $table->string('action', 160);                  // route name, or "METHOD uri" for an unnamed route
            $table->string('summary', 255);                 // the human sentence shown in the log
            $table->string('method', 7);
            $table->string('path', 255);                    // the route pattern, never the real URL (tokens live there)
            $table->string('channel', 10)->default('web');  // web, api
            $table->unsignedSmallInteger('status');         // HTTP status of the response
            $table->string('outcome', 10)->default('ok');   // ok, failed, denied

            // The record acted on, taken from the route (a bound model, or a {thing_id} segment).
            $table->string('target_type', 60)->nullable();
            $table->unsignedBigInteger('target_id')->nullable();

            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->char('device_id', 36)->nullable();

            // Append-only: no updated_at.
            $table->timestamp('created_at')->nullable();

            $table->foreign('user_id')->references('user_id')->on('users')->onDelete('set null');

            $table->index(['user_id', 'created_at']);
            $table->index(['actor_role', 'created_at']);
            $table->index(['action', 'created_at']);
            $table->index(['outcome', 'created_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_activities');
    }
};
