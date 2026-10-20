<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Unit viewings, tenant-requested or landlord-logged. One row per request, so a declined or
 * cancelled viewing stays on record rather than being overwritten when the
 * tenant asks again. See plans/unit-viewing-scheduling.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('viewing_requests', function (Blueprint $table) {
            $table->id('viewing_id');

            // Online: requested from a reservation's chat, so both ids are set.
            // Offline: the landlord logged a visit arranged by phone or in
            // person, so there is no reservation and no tenant account, only
            // the visitor's name and number.
            $table->enum('source', ['Online', 'Offline'])->default('Online');

            $table->unsignedBigInteger('reservation_id')->nullable();
            $table->foreign('reservation_id')->references('reservation_id')->on('reservations')->onDelete('cascade');

            $table->unsignedBigInteger('property_id');
            $table->foreign('property_id')->references('property_id')->on('properties')->onDelete('cascade');

            $table->unsignedBigInteger('unit_id')->nullable();
            $table->foreign('unit_id')->references('unit_id')->on('property_units')->nullOnDelete();

            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->foreign('tenant_id')->references('user_id')->on('users')->onDelete('cascade');

            $table->string('visitor_name', 120)->nullable();
            $table->string('visitor_phone', 30)->nullable();

            // Denormalised from the property so the calendar is one indexed
            // query per month instead of a join through properties.
            $table->unsignedBigInteger('landlord_id');
            $table->foreign('landlord_id')->references('user_id')->on('users')->onDelete('cascade');

            $table->dateTime('scheduled_at');

            // Completed is derived (Confirmed + time passed), never stored.
            $table->enum('status', ['Pending', 'Confirmed', 'Declined', 'Cancelled'])->default('Pending');

            // Whoever put the current time forward; the OTHER party confirms.
            $table->unsignedBigInteger('proposed_by');
            $table->foreign('proposed_by')->references('user_id')->on('users')->onDelete('cascade');

            $table->text('note')->nullable();
            $table->text('decline_reason')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            $table->index(['landlord_id', 'scheduled_at']);
            $table->index(['reservation_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('viewing_requests');
    }
};
