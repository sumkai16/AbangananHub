<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use App\Services\RentLedger;
use Illuminate\Support\Facades\Gate;

/**
 * A tenant's own tenancy and rent ledger — read-only except for the single
 * earliest unsettled period, which carries a "Pay now" action.
 */
class TenancyController extends Controller
{
    public function show(Reservation $reservation)
    {
        Gate::authorize('viewOwnTenancy', $reservation);

        $reservation->load([
            'property.landlord',
            'tenant',
            'unit.media',
            'payments.recorder',
            'depositCharges',
            'paymentClaims',
        ]);

        $ledger = RentLedger::for($reservation);

        return view('tenant.tenancy.show', [
            'reservation'    => $reservation,
            'periods'        => $ledger->periods(),
            'otherCharges'   => $ledger->otherCharges(),
            'summary'        => $ledger->summary(),
            'payablePeriod'  => $ledger->unsettledPeriods()->first(),
            // Nothing due: offer the next month so a tenant can pay ahead (direct payments only).
            'advancePeriod'  => $ledger->advancePeriod(),
        ]);
    }
}
