<?php

namespace App\Http\Controllers\Api\Landlord;

use App\Http\Controllers\Controller;
use App\Http\Resources\ReservationResource;
use App\Models\Reservation;
use App\Services\RentLedger;
use App\Services\RentReminderNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Mobile equivalent of Landlord\TenancyController — one tenancy and its rent
 * ledger, from the landlord's side. See that controller's docblock: serves
 * walk-in and platform tenancies identically.
 */
class TenancyController extends Controller
{
    public function show(Reservation $reservation): JsonResponse
    {
        Gate::authorize('viewTenancy', $reservation);

        $reservation->load([
            'tenant',
            'property',
            'unit.media',
            'payments.recorder',
            'conversation',
        ]);

        $ledger = RentLedger::for($reservation);

        return response()->json([
            'data' => [
                'reservation'       => new ReservationResource($reservation),
                'periods'           => $ledger->periods(),
                'other_charges'     => $ledger->otherCharges(),
                'summary'           => $ledger->summary(),
                'unsettled_periods' => $ledger->unsettledPeriods(),
            ],
        ]);
    }

    public function endTenancy(Request $request, Reservation $reservation): JsonResponse
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
                isset($data['move_out_date']) ? Carbon::parse($data['move_out_date']) : null
            );
        });

        if (! $ended) {
            return response()->json(['message' => 'This tenancy is not currently active, so it cannot be ended.'], 422);
        }

        return response()->json(['data' => new ReservationResource($reservation->fresh())]);
    }

    public function remind(Reservation $reservation, RentReminderNotifier $notifier): JsonResponse
    {
        Gate::authorize('viewTenancy', $reservation);

        $reservation->load(['tenant', 'property', 'unit', 'payments']);
        $tenant = $reservation->tenant;

        if (! $tenant || $tenant->is_walk_in || $tenant->account_status !== 'active') {
            return response()->json(['message' => 'This tenant has no AbangananHub account to notify.'], 422);
        }

        $target = RentLedger::for($reservation)
            ->periods()
            ->firstWhere(fn ($p) => $p['status'] !== 'paid');

        if (! $target) {
            return response()->json(['message' => $tenant->first_name.' has no outstanding rent to be reminded about.'], 422);
        }

        $notifier->sendToTenant($reservation, $target, Carbon::today());

        return response()->json(['message' => 'Rent reminder sent to '.$tenant->first_name.'.']);
    }
}
