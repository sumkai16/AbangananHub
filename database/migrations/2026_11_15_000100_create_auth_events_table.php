<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auth_events', function (Blueprint $table) {
            $table->id('event_id');

            // Nulled (not cascaded) when a user is deleted, so the trail survives. `email` keeps the address
            // typed on a failed login even when no account matched it.
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('email')->nullable();

            $table->string('event', 40);                    // see AuthEvent::EVENTS
            $table->string('channel', 20)->default('web');  // web, google, facebook, webview, api
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->char('device_id', 36)->nullable();      // the ah_device cookie, for "new device" detection
            $table->json('metadata')->nullable();           // never a password, token, code or session id

            // Append-only: no updated_at.
            $table->timestamp('created_at')->nullable();

            $table->foreign('user_id')->references('user_id')->on('users')->onDelete('set null');

            $table->index(['user_id', 'created_at']);
            $table->index(['event', 'created_at']);
            $table->index(['ip_address', 'created_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auth_events');
    }
};
