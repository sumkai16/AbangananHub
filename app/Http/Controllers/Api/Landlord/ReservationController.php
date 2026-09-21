<?php

namespace App\Http\Controllers\Api\Landlord;

use App\Http\Controllers\Controller;
use App\Http\Resources\ReservationResource;
use App\Models\Notification;
use App\Models\Reservation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ReservationController extends Controller
{
    /**
     * Reservations across all of the landlord's properties.
     * Optional ?status= filter, same statuses as the web page.
     */
    public function index(Request $request): JsonResponse
    {
        $base = Reservation::whereHas('property', function ($query) use ($request) {
            $query->where('landlord_id', $request->user()->user_id);
        });

        $counts = [
            'all'                      => (clone $base)->count(),
            'Inquiry'                  => (clone $base)->where('rental_status', 'Inquiry')->count(),
            'Under Negotiation'        => (clone $base)->where('rental_status', 'Under Negotiation')->count(),
            'Pending Rental Agreement' => (clone $base)->where('rental_status', 'Pending Rental Agreement')->count(),
            'Rental Agreement Signed'  => (clone $base)->where('rental_status', 'Rental Agreement Signed')->count(),
            'Occupied'                 => (clone $base)->where('rental_status', 'Occupied')->count(),
            'Cancelled'                => (clone $base)->where('rental_status', 'Cancelled')->count(),
            'Rejected'                 => (clone $base)->where('rental_status', 'Rejected')->count(),
        ];

        $status = $request->query('status', 'all');
        $validStatuses = ['Inquiry', 'Under Negotiation', 'Pending Rental Agreement', 'Rental Agreement Signed', 'Occupied', 'Completed', 'Cancelled', 'Rejected'];

        if (in_array($status, $validStatuses, true)) {
            $base->where('rental_status', $status);
        }

        $reservations = $base
            ->with(['property.media', 'unit', 'tenant:user_id,first_name,last_name,contact_number,profile_picture', 'conversation', 'tenantRating'])
            ->latest()
            ->paginate(10)
            ->withQueryString();

        $reservations->getCollection()->transform(fn (Reservation $r) => (new ReservationResource($r))->resolve());

        return response()->json(array_merge($reservations->toArray(), [
            'counts' => $counts,
        ]));
    }

    public function advanceToNegotiation(Request $request, Reservation $reservation): JsonResponse
    {
        Gate::authorize('advanceStatus', $reservation);

        if (! $reservation->advanceToNegotiation()) {
            throw ValidationException::withMessages(['reservation' => ['This reservation cannot move to negotiation right now.']]);
        }

        return response()->json(['data' => new ReservationResource($reservation->fresh())]);
    }

    public function advanceToPendingAgreement(Request $request, Reservation $reservation): JsonResponse
    {
        Gate::authorize('advanceStatus', $reservation);

        $request->validate([
            'agreement_terms_notes' => 'nullable|string|max:2000',
        ]);

        if (! $reservation->advanceToPendingAgreement($request->agreement_terms_notes)) {
            throw ValidationException::withMessages(['reservation' => ['This reservation cannot move to Pending Rental Agreement right now.']]);
        }

        return response()->json(['data' => new ReservationResource($reservation->fresh())]);
    }

    public function reject(Request $request, Reservation $reservation): JsonResponse
    {
        Gate::authorize('reject', $reservation);

        $request->validate([
            'rejection_reason' => 'nullable|string|max:500',
        ]);

        if (! $reservation->reject($request->rejection_reason)) {
            throw ValidationException::withMessages(['reservation' => ['This reservation can no longer be rejected.']]);
        }

        if ($reservation->unit && $reservation->unit->availability_status === 'Reserved') {
            $reservation->unit->update(['availability_status' => 'Available']);
        }

        return response()->json(['data' => new ReservationResource($reservation->fresh(['unit']))]);
    }

    public function cancel(Request $request, Reservation $reservation): JsonResponse
    {
        Gate::authorize('cancel', $reservation);

        $unitWasReserved = $reservation->unit && $reservation->unit->availability_status === 'Reserved';

        if (! $reservation->cancel()) {
            throw ValidationException::withMessages(['reservation' => ['This reservation can no longer be cancelled.']]);
        }

        if ($unitWasReserved) {
            $otherActiveExists = Reservation::where('unit_id', $reservation->unit_id)
                ->where('reservation_id', '!=', $reservation->reservation_id)
                ->whereNotIn('rental_status', Reservation::TERMINAL_STATUSES)
                ->exists();

            if (! $otherActiveExists) {
                $reservation->unit->update(['availability_status' => 'Available']);
            }
        }

        return response()->json(['data' => new ReservationResource($reservation->fresh(['unit']))]);
    }

    /**
     * Mirrors Landlord\ReservationController::markTurnedOver — same lock,
     * same system message, same notification. Starts Clock 2.
     */
    public function markTurnedOver(Reservation $reservation): JsonResponse
    {
        Gate::authorize('markTurnedOver', $reservation);

        $marked = DB::transaction(function () use ($reservation) {
            $locked = Reservation::whereKey($reservation->getKey())->lockForUpdate()->firstOrFail();

            if (! $locked->markKeysTurnedOver()) {
                return false;
            }

            $days = config('rentals.move_in_confirmation_days');

            $locked->postSystemMessage(
                "The landlord marked the keys as turned over. {$locked->tenant->name} has {$days} days to confirm move-in, after which the deposit is released automatically."
            );

            Notification::notify(
                $locked->tenant_id,
                'move_in_confirmation_due',
                'Confirm your move-in',
                "Your landlord marked the keys as turned over. Confirm your move-in within {$days} days to release your deposit.",
                route('agreements.show', $locked),
                $locked->conversation_id,
            );

            return true;
        });

        if (! $marked) {
            throw ValidationException::withMessages(['reservation' => ['Turnover cannot be marked for this reservation right now.']]);
        }

        return response()->json(['data' => new ReservationResource($reservation->fresh())]);
    }
}
