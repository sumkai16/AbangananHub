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
            ->with(['property.media', 'tenant'])
            ->latest()
            ->paginate(10);

        return view('landlord.reservations.index', compact('reservations'));
    }

    public function approve(Reservation $reservation)
    {
        Gate::authorize('approve', $reservation);

        // Update the custom status column to Approved
        $reservation->update(['status' => 'Approved']);

        // Optional custom model helper fallback if you have custom logic inside your model
        if (method_exists($reservation, 'approve')) {
            $reservation->approve();
        }

        // Lock the property status down
        $reservation->property->update(['availability_status' => 'Reserved']);

        // Cascade reject all other pending overlapping reservation requests for this specific property
        // Note: Check whether your primary key is 'reservation_id' or standard 'id'
        $primaryKey = $reservation->getKeyName();
        
        Reservation::where('property_id', $reservation->property_id)
            ->where($primaryKey, '!=', $reservation->$primaryKey)
            ->where(function($query) {
                $query->where('status', 'Pending')
                      ->orWhere('reservation_status', 'Pending'); // Safeguard for older legacy setups
            })
            ->get()
            ->each(function (Reservation $other) {
                $other->update(['status' => 'Rejected', 'rejection_reason' => 'Another applicant was approved for this space.']);
                if (method_exists($other, 'reject')) {
                    $other->reject();
                }
            });

        return back()->with('success', 'Reservation approved and cross-applications auto-declined.');
    }

    public function reject(Request $request, Reservation $reservation)
    {
        Gate::authorize('reject', $reservation);

        // Validate incoming rich context modal explanation field
        $request->validate([
            'rejection_reason' => 'nullable|string|max:500'
        ]);

        // Persist decline reason and state code
        $reservation->update([
            'status' => 'Rejected',
            'rejection_reason' => $request->rejection_reason
        ]);

        if (method_exists($reservation, 'reject')) {
            $reservation->reject();
        }

        return back()->with('error', 'Reservation request has been rejected.');
    }
}