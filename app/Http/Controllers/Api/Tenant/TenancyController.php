<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Resources\ReservationResource;
use App\Models\Reservation;
use App\Services\RentLedger;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Mobile equivalent of Tenant\TenancyController — the tenant's own rent
 * ledger. Periods/summary/otherCharges are plain arrays RentLedger already
 * derives (not Eloquent models), so they pass through as-is, same as the
 * Blade view consumes them.
 */
class TenancyController extends Controller
{
    public function show(Reservation $reservation): JsonResponse
    {
        Gate::authorize('viewOwnTenancy', $reservation);

        $reservation->load([
            'property',
            'unit.media',
            'payments.recorder',
        ]);

        $ledger = RentLedger::for($reservation);

        return response()->json([
            'data' => [
                'reservation'    => new ReservationResource($reservation),
                'periods'        => $ledger->periods(),
                'other_charges'  => $ledger->otherCharges(),
                'summary'        => $ledger->summary(),
                'payable_period' => $ledger->unsettledPeriods()->first(),
            ],
        ]);
    }
}
