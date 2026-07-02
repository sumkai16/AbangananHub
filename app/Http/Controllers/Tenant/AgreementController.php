<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AgreementController extends Controller
{
    public function show(Reservation $reservation)
    {
        Gate::authorize('viewAgreement', $reservation);

       if (!in_array($reservation->rental_status, [
            'Pending Rental Agreement',
            'Rental Agreement Signed',
            'Occupied',
        ])) {
            abort(404);
        }

        $reservation->load(['property', 'property.landlord', 'tenant', 'unit', 'payments']);
        return view('agreements.show', compact('reservation'));
    }

    public function sign(Request $request, Reservation $reservation)
    {
        Gate::authorize('sign', $reservation);

        if (!$reservation->signAgreement($request->ip())) {
            return back()->withErrors(['agreement' => 'This agreement cannot be signed right now.']);
        }

        return redirect()
            ->route('agreements.show', $reservation)
            ->with('success', 'Agreement signed. You can now proceed to payment.');
    }
}