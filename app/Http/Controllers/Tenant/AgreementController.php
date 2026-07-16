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

    $request->validate([
        'agree' => 'accepted',
        'accept_tc' => 'accepted',
    ], [
        'agree.accepted' => 'You must agree to the rental agreement terms.',
        'accept_tc.accepted' => 'You must accept the platform terms and conditions.',
    ]);

    if (!$reservation->signAgreement($request->ip())) {
        return back()->withErrors(['agreement' => 'This agreement cannot be signed right now.']);
    }

    $reservation->update(['tenant_tc_accepted_at' => now()]);

    $reservation->postSystemMessage($reservation->tenant->name . ' signed the rental agreement.');

    return redirect()
        ->route('agreements.show', $reservation)
        ->with('success', 'Agreement signed. You can now proceed to payment.');
}
public function confirmMoveIn(Reservation $reservation)
{
    Gate::authorize('sign', $reservation);

    if (!$reservation->confirmMoveIn()) {
        return back()->withErrors(['move_in' => 'Move-in cannot be confirmed right now. Payment must be completed first.']);
    }

    $reservation->postSystemMessage($reservation->tenant->name . ' confirmed move-in. The unit is now occupied.');

    return redirect()
        ->route('agreements.show', $reservation)
        ->with('success', 'Move-in confirmed! Your unit is now marked as occupied.');
}
}