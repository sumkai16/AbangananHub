<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Online tenancies never stored an agreed rent (only walk-ins did), so checkout
 * and the rent ledger read the unit's live price while the signed lease showed
 * the price at issue. From now on Reservation::advanceToPendingAgreement() pins
 * `agreed_monthly_rent` when the lease is issued; this copies the same figure
 * out of the frozen lease snapshot for tenancies that were issued before that.
 *
 * Data only, no schema change. Safe to re-run: it only fills rows still null,
 * and down() deliberately does nothing (a pinned rent is not worth unpinning).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('reservations')
            ->whereNull('agreed_monthly_rent')
            ->whereNotNull('lease_snapshot')
            ->orderBy('reservation_id')
            ->each(function ($row) {
                $snapshot = json_decode($row->lease_snapshot, true);
                $rent = is_array($snapshot) ? ($snapshot['monthly_rent'] ?? null) : null;

                if (is_numeric($rent) && (float) $rent > 0) {
                    DB::table('reservations')
                        ->where('reservation_id', $row->reservation_id)
                        ->whereNull('agreed_monthly_rent')
                        ->update(['agreed_monthly_rent' => round((float) $rent, 2)]);
                }
            });
    }

    public function down(): void
    {
        // Intentionally empty — see the class comment.
    }
};
