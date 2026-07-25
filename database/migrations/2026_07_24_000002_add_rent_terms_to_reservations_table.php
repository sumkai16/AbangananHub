<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The two terms the rent ledger needs that the reservation never carried.
 *
 * Both are nullable and both fall back (rent -> unit->rental_fee, due day ->
 * the move-in day of month), so every existing platform reservation keeps
 * working untouched and only walk-ins have to supply them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            // A walk-in rent is negotiated at the door and can differ from the
            // unit's listed price; without this the ledger's "expected" column
            // would be wrong for exactly the tenancies this feature adds.
            $table->decimal('agreed_monthly_rent', 10, 2)->nullable()->after('duration_of_stay');

            // Capped at 28 so every month actually has the day — a due day of
            // 31 would silently skip February.
            $table->unsignedTinyInteger('rent_due_day')->nullable()->after('agreed_monthly_rent');
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn(['agreed_monthly_rent', 'rent_due_day']);
        });
    }
};
