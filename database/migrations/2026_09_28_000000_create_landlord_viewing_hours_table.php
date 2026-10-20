<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The weekly hours a landlord takes viewings. One row per weekday they're
 * available; a missing weekday is a day off. A landlord with no rows at all
 * hasn't set hours yet and gets the config default (every day,
 * viewing_first_hour to viewing_last_hour). See plans/unit-viewing-scheduling.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('landlord_viewing_hours', function (Blueprint $table) {
            $table->id('viewing_hour_id');

            $table->unsignedBigInteger('landlord_id');
            $table->foreign('landlord_id')->references('user_id')->on('users')->onDelete('cascade');

            // Carbon's dayOfWeek: 0 = Sunday … 6 = Saturday.
            $table->unsignedTinyInteger('day_of_week');

            // Whole hours, 24h. Viewings start from start_hour; the last one
            // starts an hour before end_hour.
            $table->unsignedTinyInteger('start_hour');
            $table->unsignedTinyInteger('end_hour');

            $table->timestamps();

            $table->unique(['landlord_id', 'day_of_week']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('landlord_viewing_hours');
    }
};
