<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Property;
use App\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\PropertyUnit;
class ReservationController extends Controller
{
    public function index(Request $request)
    {
        $base = Reservation::where('tenant_id', Auth::id());

        $counts = [
            'all'                       => (clone $base)->count(),
            'Inquiry'                   => (clone $base)->where('rental_status', 'Inquiry')->count(),
            'Under Negotiation'         => (clone $base)->where('rental_status', 'Under Negotiation')->count(),
            'Pending Rental Agreement'  => (clone $base)->where('rental_status', 'Pending Rental Agreement')->count(),
            'Rental Agreement Signed'   => (clone $base)->where('rental_status', 'Rental Agreement Signed')->count(),
            'Occupied'                  => (clone $base)->where('rental_status', 'Occupied')->count(),
            'Cancelled'                 => (clone $base)->where('rental_status', 'Cancelled')->count(),
            'Rejected'                  => (clone $base)->where('rental_status', 'Rejected')->count(),
        ];

        $status = $request->query('status', 'all');
        $validStatuses = ['Inquiry', 'Under Negotiation', 'Pending Rental Agreement', 'Rental Agreement Signed', 'Occupied', 'Cancelled', 'Rejected'];

        if (in_array($status, $validStatuses, true)) {
            $base->where('rental_status', $status);
        }

        $reservations = $base
            ->with(['property.media', 'unit', 'tenant', 'conversation'])
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('tenant.reservations.index', compact('reservations', 'counts', 'status'));
    }

public function store(Request $request)
    {
        $request->validate([
            'unit_id' => 'required|integer|exists:property_units,unit_id',
        ]);

        $unit = \App\Models\PropertyUnit::where('unit_id', $request->unit_id)
            ->where('availability_status', 'Available')
            ->where('verification_status', 'Approved')
            ->first();

        if (!$unit) {
            return back()->withErrors(['unit' => 'This unit is not available.']);
        }

        $property = $unit->property;

        if ($property->landlord_id === Auth::id()) {
            return back()->withErrors(['property' => 'You cannot inquire on your own listing.']);
        }

        if ($property->verification_status !== 'Approved') {
            return back()->withErrors(['property' => 'This property is not available.']);
        }

        // Check for existing active reservation on this unit by this tenant
        $exists = Reservation::where('unit_id', $unit->unit_id)
            ->where('tenant_id', Auth::id())
            ->whereNotIn('rental_status', ['Cancelled', 'Rejected'])
            ->exists();

        if ($exists) {
            return back()->withErrors(['unit' => 'You already have an active inquiry or reservation for this unit.']);
        }

        $conversation = Conversation::firstOrCreate([
            'tenant_id'   => Auth::id(),
            'landlord_id' => $property->landlord_id,
            'property_id' => $property->property_id,
            'unit_id'     => $unit->unit_id,
        ]);

        Reservation::create([
            'property_id'      => $property->property_id,
            'unit_id'          => $unit->unit_id,
            'tenant_id'        => Auth::id(),
            'conversation_id'  => $conversation->conversation_id,
            'reservation_date' => now(),
            'rental_status'    => 'Inquiry',
        ]);

        return redirect()->route('conversations.show', $conversation)
            ->with('success', 'Inquiry started — discuss the details with your landlord.');
    }

    public function cancel(Reservation $reservation)
    {
        if ($reservation->tenant_id !== Auth::id()) {
            abort(403);
        }

        if (!$reservation->cancel()) {
            return back()->withErrors(['reservation' => 'This reservation can no longer be cancelled.']);
        }

        // Release unit if it was reserved for this tenant
        if ($reservation->unit && $reservation->unit->availability_status === 'Reserved') {
            $reservation->unit->update(['availability_status' => 'Available']);
        }

        return back()->with('success', 'Reservation cancelled.');
    }
}