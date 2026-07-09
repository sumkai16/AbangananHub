<?php

namespace App\Http\Controllers\Api\Landlord;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use App\Models\TenantRating;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TenantRatingController extends Controller
{
    /**
     * Check rating status for a reservation: whether the landlord can
     * still rate, and the existing rating if one was already given.
     */
    public function show(Request $request, Reservation $reservation): JsonResponse
    {
        $this->authorizeReservation($request, $reservation);

        $rating = $reservation->tenantRating;

        return response()->json([
            'data' => [
                'already_rated' => (bool) $rating,
                'can_rate'      => ! $rating && $reservation->rental_status === 'Occupied',
                'rating'        => $rating,
                'tenant'        => $reservation->tenant()->select('user_id', 'first_name', 'last_name', 'profile_picture')->first(),
            ],
        ]);
    }

    /**
     * Submit a tenant rating. Same rules as the web
     * Landlord\TenantRatingController@store.
     */
    public function store(Request $request, Reservation $reservation): JsonResponse
    {
        $this->authorizeReservation($request, $reservation);

        if ($reservation->rental_status !== 'Occupied') {
            throw ValidationException::withMessages(['reservation' => ['You can only rate tenants for occupied rentals.']]);
        }

        if ($reservation->tenantRating) {
            throw ValidationException::withMessages(['reservation' => ['You have already rated this tenant for this reservation.']]);
        }

        $validated = $request->validate([
            'rating'  => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000',
        ]);

        $rating = TenantRating::create([
            'reservation_id' => $reservation->reservation_id,
            'landlord_id'    => $request->user()->user_id,
            'tenant_id'      => $reservation->tenant_id,
            'rating'         => $validated['rating'],
            'comment'        => $validated['comment'] ?? null,
        ]);

        return response()->json(['data' => $rating], 201);
    }

    private function authorizeReservation(Request $request, Reservation $reservation): void
    {
        $property = $reservation->property;

        if (! $property || $property->landlord_id !== $request->user()->user_id) {
            abort(403);
        }
    }
}
