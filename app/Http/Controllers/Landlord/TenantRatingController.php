<?php

namespace App\Http\Controllers\Landlord;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use App\Models\TenantRating;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TenantRatingController extends Controller
{
    public function create(Reservation $reservation)
    {
        // Ensure this reservation belongs to a property owned by the logged-in landlord
        $landlord = Auth::user();
        $property = $reservation->property;

        if (!$property || $property->landlord_id !== $landlord->user_id) {
            abort(403);
        }

        if ($reservation->rental_status !== 'Occupied') {
            return back()->with('error', 'You can only rate tenants for occupied rentals.');
        }

        if ($reservation->tenantRating) {
            return back()->with('error', 'You have already rated this tenant for this reservation.');
        }

        $reservation->load('tenant', 'unit');

        return view('landlord.tenant-ratings.create', compact('reservation'));
    }

    public function store(Request $request, Reservation $reservation)
    {
        $landlord = Auth::user();
        $property = $reservation->property;

        if (!$property || $property->landlord_id !== $landlord->user_id) {
            abort(403);
        }

        if ($reservation->rental_status !== 'Occupied') {
            return back()->with('error', 'You can only rate tenants for occupied rentals.');
        }

        if ($reservation->tenantRating) {
            return back()->with('error', 'You have already rated this tenant for this reservation.');
        }

        $validated = $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000',
        ]);

        TenantRating::create([
            'reservation_id' => $reservation->reservation_id,
            'landlord_id' => $landlord->user_id,
            'tenant_id' => $reservation->tenant_id,
            'rating' => $validated['rating'],
            'comment' => $validated['comment'] ?? null,
        ]);

        return redirect()->back()->with('success', 'Tenant rated successfully.');
    }
}