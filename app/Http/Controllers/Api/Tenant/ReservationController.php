<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\PropertyUnit;
use App\Models\Reservation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ReservationController extends Controller
{
    /**
     * The requesting tenant's reservations with property/unit info.
     * Optional ?status= filter, same statuses as the web page.
     */
    public function index(Request $request): JsonResponse
    {
        $base = Reservation::where('tenant_id', $request->user()->user_id);

        $counts = $this->statusCounts($base);

        $status = $request->query('status', 'all');
        if (in_array($status, array_keys(array_slice($counts, 1)), true)) {
            $base->where('rental_status', $status);
        }

        $reservations = $base
            ->with(['property.media', 'property.landlord:user_id,first_name,last_name,contact_number,profile_picture', 'unit', 'conversation'])
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return response()->json(array_merge($reservations->toArray(), [
            'counts' => $counts,
        ]));
    }

    /**
     * Start an inquiry/reservation on a unit.
     * Same business rules and conversation handling as the web
     * Tenant\ReservationController@store.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'unit_id'              => 'required|integer|exists:property_units,unit_id',
            'target_move_in_date'  => 'nullable|date|after_or_equal:today',
            'target_move_out_date' => 'nullable|date|after:target_move_in_date',
            'remarks'              => 'nullable|string|max:300',
            'message'              => 'nullable|string|max:300',
        ]);

        $tenantId = $request->user()->user_id;

        $unit = PropertyUnit::where('unit_id', $request->unit_id)
            ->where('availability_status', 'Available')
            ->where('verification_status', 'Approved')
            ->first();

        if (! $unit) {
            throw ValidationException::withMessages(['unit' => ['This unit is not available.']]);
        }

        $property = $unit->property;

        if ($property->landlord_id === $tenantId) {
            throw ValidationException::withMessages(['property' => ['You cannot inquire on your own listing.']]);
        }

        if ($property->verification_status !== 'Approved') {
            throw ValidationException::withMessages(['property' => ['This property is not available.']]);
        }

        $exists = Reservation::where('unit_id', $unit->unit_id)
            ->where('tenant_id', $tenantId)
            ->whereNotIn('rental_status', Reservation::TERMINAL_STATUSES)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages(['unit' => ['You already have an active inquiry or reservation for this unit.']]);
        }

        $conversation = Conversation::where('tenant_id', $tenantId)
            ->where('landlord_id', $property->landlord_id)
            ->where('property_id', $property->property_id)
            ->where('status', '!=', 'Cancelled')
            ->first();

        if ($conversation) {
            if ($conversation->unit_id !== $unit->unit_id) {
                $conversation->update(['unit_id' => $unit->unit_id]);
            }
        } else {
            $conversation = Conversation::create([
                'tenant_id'   => $tenantId,
                'landlord_id' => $property->landlord_id,
                'property_id' => $property->property_id,
                'unit_id'     => $unit->unit_id,
            ]);
        }

        $reservation = Reservation::create([
            'property_id'          => $property->property_id,
            'unit_id'              => $unit->unit_id,
            'tenant_id'            => $tenantId,
            'conversation_id'      => $conversation->conversation_id,
            'reservation_date'     => now(),
            'rental_status'        => 'Inquiry',
            'target_move_in_date'  => $request->target_move_in_date,
            'target_move_out_date' => $request->target_move_out_date,
            'remarks'              => $request->remarks,
        ]);

        // Optional first message (accept either key for client convenience)
        $firstMessage = $request->input('message', $request->input('remarks'));
        if ($request->filled('message')) {
            $conversation->messages()->create([
                'sender_id' => $tenantId,
                'message'   => $firstMessage,
            ]);
        }

        return response()->json([
            'data' => $reservation->load(['property.media', 'unit', 'conversation']),
        ], 201);
    }

    /**
     * Cancel the tenant's own reservation.
     */
    public function cancel(Request $request, Reservation $reservation): JsonResponse
    {
        if ($reservation->tenant_id !== $request->user()->user_id) {
            abort(403);
        }

        if (! $reservation->cancel()) {
            throw ValidationException::withMessages(['reservation' => ['This reservation can no longer be cancelled.']]);
        }

        if ($reservation->unit && $reservation->unit->availability_status === 'Reserved') {
            $reservation->unit->update(['availability_status' => 'Available']);
        }

        return response()->json(['data' => $reservation->fresh(['unit'])]);
    }

    private function statusCounts($base): array
    {
        return [
            'all'                      => (clone $base)->count(),
            'Inquiry'                  => (clone $base)->where('rental_status', 'Inquiry')->count(),
            'Under Negotiation'        => (clone $base)->where('rental_status', 'Under Negotiation')->count(),
            'Pending Rental Agreement' => (clone $base)->where('rental_status', 'Pending Rental Agreement')->count(),
            'Rental Agreement Signed'  => (clone $base)->where('rental_status', 'Rental Agreement Signed')->count(),
            'Occupied'                 => (clone $base)->where('rental_status', 'Occupied')->count(),
            'Cancelled'                => (clone $base)->where('rental_status', 'Cancelled')->count(),
            'Rejected'                 => (clone $base)->where('rental_status', 'Rejected')->count(),
        ];
    }
}
