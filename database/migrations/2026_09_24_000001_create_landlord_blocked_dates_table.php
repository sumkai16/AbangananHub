<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Days a landlord isn't available for viewings. Per landlord, not per
 * property: the landlord is the one who can't show up.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('landlord_blocked_dates', function (Blueprint $table) {
            $table->id('blocked_date_id');

            $table->unsignedBigInteger('landlord_id');
            $table->foreign('landlord_id')->references('user_id')->on('users')->onDelete('cascade');

            $table->date('date');
            $table->string('reason', 255)->nullable();
            $table->timestamps();

            $table->unique(['landlord_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('landlord_blocked_dates');
    }
};
