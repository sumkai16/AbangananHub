<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Resources\ReservationResource;
use App\Models\Notification;
use App\Models\Reservation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Mobile equivalent of Tenant\AgreementController. Same gates, same locked
 * transactions, same system messages — this is a second entry point onto
 * Reservation's transition methods, not a second implementation of them.
 */
class AgreementController extends Controller
{
    public function show(Reservation $reservation): JsonResponse
    {
        Gate::authorize('viewAgreement', $reservation);

        if (! in_array($reservation->rental_status, [
            'Pending Rental Agreement',
            'Rental Agreement Signed',
            'Occupied',
        ], true)) {
            abort(404);
        }

        $reservation->load(['property', 'property.landlord', 'tenant', 'unit', 'payments']);

        if (! $reservation->property || ! $reservation->unit) {
            abort(404, 'This unit is no longer available.');
        }

        return response()->json(['data' => new ReservationResource($reservation)]);
    }

    public function sign(Request $request, Reservation $reservation): JsonResponse
    {
        Gate::authorize('sign', $reservation);

        $request->validate([
            'agree'     => 'accepted',
            'accept_tc' => 'accepted',
        ], [
            'agree.accepted'     => 'You must agree to the rental agreement terms.',
            'accept_tc.accepted' => 'You must accept the platform terms and conditions.',
        ]);

        $signed = DB::transaction(function () use ($reservation, $request) {
            $locked = Reservation::whereKey($reservation->getKey())->lockForUpdate()->firstOrFail();

            if (! $locked->signAgreement($request->ip())) {
                return false;
            }

            $locked->update(['tenant_tc_accepted_at' => now()]);
            $locked->postSystemMessage($locked->tenant->name.' signed the rental agreement.');

            return true;
        });

        if (! $signed) {
            throw ValidationException::withMessages(['reservation' => ['This agreement cannot be signed right now.']]);
        }

        return response()->json(['data' => new ReservationResource($reservation->fresh())]);
    }

    public function confirmMoveIn(Reservation $reservation): JsonResponse
    {
        Gate::authorize('sign', $reservation);

        $released = DB::transaction(function () use ($reservation) {
            $locked = Reservation::whereKey($reservation->getKey())->lockForUpdate()->firstOrFail();

            if (! $locked->confirmMoveIn()) {
                return null;
            }

            $landlord = $locked->property?->landlord;
            $landlordName = $landlord ? trim($landlord->first_name.' '.$landlord->last_name) : 'the landlord';

            $locked->postSystemMessage(
                $locked->tenant->name." confirmed move-in. The unit is now occupied and the payment has been released to {$landlordName}."
            );

            return $locked->releasedPayment;
        });

        if (! $released) {
            throw ValidationException::withMessages(['reservation' => ['Move-in cannot be confirmed right now. Your payment must be completed first.']]);
        }

        return response()->json(['data' => new ReservationResource($reservation->fresh())]);
    }

    public function disputeMoveIn(Request $request, Reservation $reservation): JsonResponse
    {
        Gate::authorize('sign', $reservation);

        $validated = $request->validate([
            'reason' => 'required|string|min:10|max:1000',
        ], [
            'reason.required' => 'Please tell us what happened so an admin can help.',
            'reason.min'      => 'Please give us a little more detail — at least 10 characters.',
        ]);

        $disputed = DB::transaction(function () use ($reservation, $validated) {
            $locked = Reservation::whereKey($reservation->getKey())->lockForUpdate()->firstOrFail();

            if (! $locked->disputeMoveIn($validated['reason'])) {
                return false;
            }

            $locked->postSystemMessage(
                $locked->tenant->name.' reported an issue with the move-in. The deposit is on hold pending review by an administrator.'
            );

            Notification::notify(
                $locked->property?->landlord_id,
                'move_in_disputed',
                'Move-in issue reported',
                $locked->tenant->name.' reported that the move-in has not happened. An administrator will review this.',
                route('landlord.reservations.index'),
                $locked->conversation_id,
            );

            return true;
        });

        if (! $disputed) {
            throw ValidationException::withMessages(['reservation' => ['This move-in cannot be disputed right now.']]);
        }

        return response()->json(['data' => new ReservationResource($reservation->fresh())]);
    }
}
