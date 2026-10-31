<?php

namespace App\Http\Controllers\Landlord;

use App\Http\Controllers\Controller;
use App\Models\LandlordTenant;
use App\Models\Reservation;
use App\Services\RentLedger;
use Illuminate\Support\Facades\Gate;

/**
 * One tenancy's rent ledger, under Payments: what is owed month by month, what came in, payment reports to answer,
 * and the deposit. Recording, voiding and deposit charges post to their own routes and come back here. The
 * contract side (lease, tenant details, ending it) is TenancyController::show, under Contracts.
 *
 * Serves walk-in and online tenancies identically: both are an occupied unit with rent falling due every month.
 */
class LedgerController extends Controller
{
    public function show(Reservation $reservation)
    {
        Gate::authorize('viewTenancy', $reservation);

        // Eager-loaded up front: the ledger reads payments, the header reads the tenant and unit, and
        // Model::preventLazyLoading is on in dev.
        $reservation->load([
            'tenant',
            'property',
            'unit.media',
            'payments.recorder',
            'payments.voider',
            'payments.replacements',
            'depositCharges.charger',
            'depositCharges.voider',
            'paymentClaims',
            'renewals',
        ]);

        $ledger = RentLedger::for($reservation);

        return view('landlord.payments.ledger', [
            'reservation' => $reservation,
            'tenantCode' => LandlordTenant::where('landlord_id', $reservation->property->landlord_id)
                ->where('tenant_id', $reservation->tenant_id)->first()?->code(),
            'ledger' => $ledger,
            'periods' => $ledger->periods(),
            'otherCharges' => $ledger->otherCharges(),
            'summary' => $ledger->summary(),
            'unsettledPeriods' => $ledger->unsettledPeriods(),
            'monthlyTransactions' => $ledger->monthlyTransactions(),
            'voidedTransactions' => $ledger->voidedTransactions(),
        ]);
    }
}
