<?php

namespace App\Http\Controllers\Landlord;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class ReservationController extends Controller
{
    public function index()
    {
        $reservations = Reservation::whereHas('property', function ($query) {
                $query->where('landlord_id', Auth::id());
            })
            ->with(['property.media', 'property.units', 'unit', 'tenant'])
            ->latest()
            ->paginate(10);

        return view('landlord.reservations.index', compact('reservations'));
    }

    public function approve(Reservation $reservation)
    {
        Gate::authorize('approve', $reservation);

        if (!$reservation->approve()) {
            return back()->withErrors(['reservation' => 'This reservation can no longer be approved.']);
        }

        $reservation->unit->update(['availability_status' => 'Reserved']);

        Reservation::where('unit_id', $reservation->unit_id)
            ->where('reservation_id', '!=', $reservation->reservation_id)
            ->where('reservation_status', 'Pending')
            ->update([
                'reservation_status' => 'Rejected',
                'rejection_reason'   => 'Another applicant was approved for this unit.',
            ]);

        return back()->with('success', 'Reservation approved and competing requests auto-declined.');
    }

    public function reject(Request $request, Reservation $reservation)
    {
        Gate::authorize('reject', $reservation);

        if (!$reservation->isPending()) {
            return back()->withErrors(['reservation' => 'This reservation can no longer be rejected.']);
        }

        $request->validate([
            'rejection_reason' => 'nullable|string|max:500',
        ]);

        $reservation->update([
            'reservation_status' => 'Rejected',
            'rejection_reason'   => $request->rejection_reason,
        ]);

        return back()->with('success', 'Reservation rejected.');
    }
}