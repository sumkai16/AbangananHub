<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_holds', function (Blueprint $table) {
            $table->id('hold_id');

            $table->unsignedBigInteger('tenant_id');
            // Null when an admin created the hold (a duplicate account), or after the landlord's account is deleted.
            $table->unsignedBigInteger('landlord_id')->nullable();
            $table->unsignedBigInteger('reservation_id')->nullable();
            // The hold this one was copied from when an admin put a duplicate account on hold.
            $table->unsignedBigInteger('source_hold_id')->nullable();

            $table->decimal('amount_owed', 12, 2)->default(0);  // snapshot when flagged
            $table->text('reason');
            $table->string('status', 20)->default('pending_admin'); // see TenantHold::STATUSES
            $table->text('tenant_note')->nullable();                // the tenant's side, from the hold page

            $table->timestamp('flagged_at')->nullable();
            $table->unsignedBigInteger('confirmed_by')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->text('rejected_note')->nullable();
            $table->unsignedBigInteger('released_by')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->string('release_reason', 20)->nullable();       // see TenantHold::RELEASE_REASONS

            $table->timestamps();

            $table->foreign('tenant_id')->references('user_id')->on('users')->onDelete('cascade');
            $table->foreign('landlord_id')->references('user_id')->on('users')->onDelete('set null');
            $table->foreign('reservation_id')->references('reservation_id')->on('reservations')->onDelete('set null');
            $table->foreign('source_hold_id')->references('hold_id')->on('tenant_holds')->onDelete('set null');
            $table->foreign('confirmed_by')->references('user_id')->on('users')->onDelete('set null');
            $table->foreign('released_by')->references('user_id')->on('users')->onDelete('set null');

            $table->index(['tenant_id', 'status']);
            $table->index(['landlord_id', 'status']);
            $table->index('status');
        });

        Schema::create('hold_match_alerts', function (Blueprint $table) {
            $table->id('alert_id');

            $table->unsignedBigInteger('hold_id');
            $table->unsignedBigInteger('user_id');      // the new account that looks like the held tenant
            $table->json('signals');                    // e.g. ["phone","device"]; never the other person's details
            $table->string('status', 20)->default('open'); // open, dismissed, held
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();

            $table->timestamps();

            $table->foreign('hold_id')->references('hold_id')->on('tenant_holds')->onDelete('cascade');
            $table->foreign('user_id')->references('user_id')->on('users')->onDelete('cascade');
            $table->foreign('reviewed_by')->references('user_id')->on('users')->onDelete('set null');

            // One alert per held tenant and new account, so repeated sign-ins do not pile up.
            $table->unique(['hold_id', 'user_id']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hold_match_alerts');
        Schema::dropIfExists('tenant_holds');
    }
};
