<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * rental_status was a free varchar, so a typo or a retired value could be written without error
 * (the same class of bug as users.account_status). It is now an ENUM of the nine states the app
 * actually uses. 'Reserved' is the walk-in future-dated state; it was in the code but not the docs.
 * up() refuses to run if any row holds a value outside the list, naming it, rather than letting
 * MySQL coerce it.
 */
return new class extends Migration
{
    private const STATUSES = [
        'Inquiry', 'Under Negotiation', 'Pending Rental Agreement', 'Rental Agreement Signed',
        'Reserved', 'Occupied', 'Completed', 'Rejected', 'Cancelled',
    ];

    public function up(): void
    {
        // MySQL-only enum change — see 2026_07_16_112509_update_payment_status_enum.
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $unknown = DB::table('reservations')
            ->whereNotIn('rental_status', self::STATUSES)
            ->distinct()
            ->pluck('rental_status');

        if ($unknown->isNotEmpty()) {
            throw new RuntimeException('reservations.rental_status holds values outside the enum: '.$unknown->implode(', '));
        }

        $list = "'".implode("','", self::STATUSES)."'";
        DB::statement("ALTER TABLE reservations MODIFY rental_status ENUM({$list}) NOT NULL DEFAULT 'Inquiry'");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE reservations MODIFY rental_status VARCHAR(255) NOT NULL DEFAULT 'Inquiry'");
    }
};
