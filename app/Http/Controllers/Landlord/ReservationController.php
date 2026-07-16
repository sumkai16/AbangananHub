<?php

namespace App\Http\Controllers\Landlord;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class ReservationController extends Controller
{
    public function index(Request $request)
    {
        $base = Reservation::whereHas('property', function ($query) {
            $query->where('landlord_id', Auth::id());
        });

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
            ->with(['property.media', 'property.landlord', 'unit', 'tenant', 'conversation', 'tenantRating'])
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('landlord.reservations.index', compact('reservations', 'counts', 'status'));
    }

    public function reject(Request $request, Reservation $reservation)
    {
        Gate::authorize('reject', $reservation);

        $request->validate([
            'rejection_reason' => 'nullable|string|max:500',
        ]);

        if (!$reservation->reject($request->rejection_reason)) {
            return back()->withErrors(['reservation' => 'This reservation can no longer be rejected.']);
        }

        // Release unit if it was reserved
        if ($reservation->unit && $reservation->unit->availability_status === 'Reserved') {
            $reservation->unit->update(['availability_status' => 'Available']);
        }

        return back()->with('success', 'Reservation rejected.');
    }

    public function cancel(Reservation $reservation)
    {
        Gate::authorize('cancel', $reservation);

        $unitWasReserved = $reservation->unit && $reservation->unit->availability_status === 'Reserved';

        if (!$reservation->cancel()) {
            return back()->withErrors(['reservation' => 'This reservation can no longer be cancelled.']);
        }

        if ($unitWasReserved) {
            $otherActiveExists = Reservation::where('unit_id', $reservation->unit_id)
                ->where('reservation_id', '!=', $reservation->reservation_id)
                ->whereNotIn('rental_status', ['Cancelled', 'Rejected'])
                ->exists();

            if (!$otherActiveExists) {
                $reservation->unit->update(['availability_status' => 'Available']);
            }
        }

        return back()->with('success', 'Reservation cancelled.');
    }

    public function advanceToNegotiation(Reservation $reservation)
    {
        Gate::authorize('advanceStatus', $reservation);

        if (!$reservation->advanceToNegotiation()) {
            return back()->withErrors(['reservation' => 'This reservation cannot move to negotiation right now.']);
        }

        $reservation->postSystemMessage(Auth::user()->name . ' accepted the inquiry and started negotiation.');

        return back()->with('success', 'Moved to Under Negotiation.');
    }

public function advanceToPendingAgreement(Request $request, Reservation $reservation)
{
    Gate::authorize('advanceStatus', $reservation);

    $request->validate([
        'agreement_terms_notes' => 'nullable|string|max:2000',
        'accept_tc' => 'accepted',
    ], [
        'accept_tc.accepted' => 'You must accept the terms and conditions to proceed.',
    ]);

    if (!$reservation->advanceToPendingAgreement($request->agreement_terms_notes)) {
        return back()->withErrors(['reservation' => 'This reservation cannot move to Pending Rental Agreement right now.']);
    }

    $reservation->update(['landlord_tc_accepted_at' => now()]);

    $reservation->postSystemMessage(Auth::user()->name . ' sent the rental agreement.');

    return back()->with('success', 'Agreement sent to tenant.');
}
}