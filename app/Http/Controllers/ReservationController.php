<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReservationRequest;
use App\Models\Property;
use App\Models\Reservation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class ReservationController extends Controller
{
    public function index()
    {
        $reservations = Reservation::where('tenant_id', Auth::id())
            ->with(['property.media', 'property.landlord'])
            ->latest()
            ->paginate(10);

        return view('reservations.index', compact('reservations'));
    }

public function store(StoreReservationRequest $request, Property $property)
{
    if ($property->landlord_id === Auth::id()) {
        return back()->withErrors(['property' => 'You cannot reserve your own listing.']);
    }

    if ($property->verification_status !== 'Approved' || $property->availability_status !== 'Available') {
        return back()->withErrors(['property' => 'This property is not currently available for reservation.']);
    }

    $exists = Reservation::where('property_id', $property->property_id)
        ->where('tenant_id', Auth::id())
        ->whereIn('reservation_status', ['Pending', 'Approved'])
        ->exists();

    if ($exists) {
        return back()->withErrors(['property' => 'You already have an active reservation request for this property.']);
    }

    // Save all custom inquiry parameters cleanly into your database
    Reservation::create([
        'property_id'        => $property->property_id,
        'tenant_id'          => Auth::id(),
        'reservation_date'   => $request->validated('reservation_date'),
        'duration_of_stay'   => $request->validated('duration_of_stay'),
        'occupants_count'    => $request->validated('occupants_count'),
        'reservation_status' => 'Pending',
        'remarks'            => $request->validated('remarks'),
    ]);

    return redirect()->route('reservations.index')->with('success', 'Reservation request submitted.');
}

    public function cancel(Reservation $reservation)
    {
        Gate::authorize('cancel', $reservation);

        $wasApproved = $reservation->isApproved();

        if (!$reservation->cancel()) {
            return back()->withErrors(['reservation' => 'This reservation can no longer be cancelled.']);
        }

        if ($wasApproved && $reservation->property) {
            $reservation->property->update(['availability_status' => 'Available']);
        }

        return back()->with('success', 'Reservation cancelled.');
    }
}