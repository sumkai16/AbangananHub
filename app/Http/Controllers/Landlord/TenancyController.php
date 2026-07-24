<?php

namespace App\Http\Controllers\Landlord;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use App\Services\RentLedger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * One tenancy and its rent ledger, from the landlord's side.
 *
 * Serves walk-in and platform tenancies identically: both are an occupied unit
 * with rent falling due every month, and the escrow only ever covered the
 * initial payment. The difference between them is presentational — a walk-in is
 * badged as landlord-asserted — not structural.
 */
class TenancyController extends Controller
{
    public function show(Reservation $reservation)
    {
        Gate::authorize('viewTenancy', $reservation);

        // Eager-loaded up front: the ledger reads payments, the header reads
        // the tenant and unit, and Model::preventLazyLoading is on in dev.
        $reservation->load([
            'tenant',
            'property',
            'unit.media',
            'payments.recorder',
            'conversation',
        ]);

        $ledger = RentLedger::for($reservation);

        return view('landlord.tenancies.show', [
            'reservation'      => $reservation,
            'ledger'           => $ledger,
            'periods'          => $ledger->periods(),
            'otherCharges'     => $ledger->otherCharges(),
            'summary'          => $ledger->summary(),
            'unsettledPeriods' => $ledger->unsettledPeriods(),
        ]);
    }

    /**
     * Close out the tenancy and hand the unit back to the available pool.
     *
     * Locked and re-checked inside the transaction per RULES.md: this flips a
     * status and frees a unit as one consequential transition, and a
     * double-submit could otherwise release a unit a second tenancy had already
     * claimed in between.
     */
    public function endTenancy(Request $request, Reservation $reservation)
    {
        Gate::authorize('endTenancy', $reservation);

        $data = $request->validate([
            'move_out_date' => ['nullable', 'date', 'before_or_equal:today'],
        ], [
            'move_out_date.before_or_equal' => 'A move-out date cannot be in the future.',
        ]);

        $ended = DB::transaction(function () use ($reservation, $data) {
            $locked = Reservation::whereKey($reservation->getKey())
                ->with(['unit', 'property'])
                ->lockForUpdate()
                ->firstOrFail();

            return $locked->endTenancy(
                isset($data['move_out_date']) ? \Illuminate\Support\Carbon::parse($data['move_out_date']) : null
            );
        });

        if (! $ended) {
            return back()->with('error', 'This tenancy is not currently active, so it cannot be ended.');
        }

        return redirect()
            ->route('landlord.tenancies.show', $reservation)
            ->with('success', 'Tenancy ended. The unit is available again and the ledger is closed.');
    }
}
