<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * A monthly rental always carries a security deposit, so the column stops
     * being optional from here on — both unit controllers now validate it as
     * `required`. Units created before that rule existed have a NULL deposit,
     * which would render as a blank on the listing and the agreement.
     *
     * Backfilled to one month's rent, the standard arrangement in the coverage
     * area. Landlords can edit it; the point is that no live listing shows a
     * tenant an empty deposit line.
     *
     * The column stays nullable at the DB level. The requirement is an
     * application rule (same as every other business rule in this app), and a
     * NOT NULL constraint would only turn a validation message into a 500 on
     * any path that hasn't been updated yet.
     *
     * LEAST() guards the width difference: rental_fee is decimal(10,2) but
     * security_deposit is decimal(8,2), so an eight-figure rent would overflow.
     */
    public function up(): void
    {
        DB::table('property_units')
            ->whereNull('security_deposit')
            ->update(['security_deposit' => DB::raw('LEAST(rental_fee, 999999.99)')]);
    }

    /**
     * Irreversible by design — the pre-migration NULLs are not recorded
     * anywhere, so there is nothing to restore them from. Nulling every
     * deposit back out would destroy the amounts landlords have since edited.
     */
    public function down(): void
    {
        //
    }
};
