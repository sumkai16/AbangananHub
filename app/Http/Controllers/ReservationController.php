<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReservationRequest;
use App\Models\Property;
use App\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class ReservationController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status');
        $search = $request->query('search');

        $query = Reservation::where('tenant_id', Auth::id())
            ->with(['property.media', 'property.landlord'])
            ->latest();

        if ($status && $status !== 'All') {
            $query->where('reservation_status', $status);
        }

        if ($search) {
            $query->whereHas('property', fn($q) => $q->where('title', 'like', "%{$search}%")
                ->orWhere('address', 'like', "%{$search}%"));
        }

        $reservations = $query->paginate(10)->withQueryString();

        return view('reservations.index', compact('reservations'));
    }

public function store(StoreReservationRequest $request, Property $property)
{
    if ($property->landlord_id === Auth::id()) {
        return back()->withErrors(['property' => 'You cannot reserve your own listing.']);
    }

    if ($property->verification_status !== 'Approved') {
        return back()->withErrors(['property' => 'This property is not available for reservation.']);
    }

    $unit = $property->units()
        ->where('unit_id', $request->unit_id)
        ->where('availability_status', 'Available')
        ->first();

    if (!$unit) {
        return back()->withErrors(['unit' => 'This unit is not available for reservation.']);
    }
   if ($request->occupants_count > $unit->occupancy_limit) {
        return back()->withErrors(['occupants_count' => "This unit fits a maximum of {$unit->occupancy_limit} occupants."]);
    }
    $exists = Reservation::where('unit_id', $unit->unit_id)
        ->where('tenant_id', Auth::id())
        ->whereIn('reservation_status', ['Pending', 'Approved'])
        ->exists();

    if ($exists) {
        return back()->withErrors(['unit' => 'You already have an active reservation for this unit.']);
    }

    Reservation::create([
        'property_id'        => $property->property_id,
        'unit_id'            => $unit->unit_id,
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

    if ($wasApproved && $reservation->unit) {
        $reservation->unit->update(['availability_status' => 'Available']);
    }

    return back()->with('success', 'Reservation cancelled.');
}


}