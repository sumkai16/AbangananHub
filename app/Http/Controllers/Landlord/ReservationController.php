<?php

namespace App\Http\Controllers\Landlord;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class ReservationController extends Controller
{
    public function index()
    {
        $reservations = Reservation::whereHas('property', function ($query) {
                $query->where('landlord_id', Auth::id());
            })
            ->with(['property', 'tenant'])
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

        $reservation->property->update(['availability_status' => 'Reserved']);

        Reservation::where('property_id', $reservation->property_id)
            ->where('reservation_id', '!=', $reservation->reservation_id)
            ->where('reservation_status', 'Pending')
            ->get()
            ->each(fn (Reservation $other) => $other->reject());

        return back()->with('success', 'Reservation approved.');
    }

    public function reject(Reservation $reservation)
    {
        Gate::authorize('reject', $reservation);

        if (!$reservation->reject()) {
            return back()->withErrors(['reservation' => 'This reservation can no longer be rejected.']);
        }

        return back()->with('success', 'Reservation rejected.');
    }
}