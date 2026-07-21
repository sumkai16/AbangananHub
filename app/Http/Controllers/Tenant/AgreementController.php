<?php

namespace App\Http\Controllers\Tenant;

use App\Events\PaymentStatusUpdated;
use App\Http\Controllers\Controller;
use App\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

        if (!$reservation->property || !$reservation->unit) {
            abort(404, 'This unit is no longer available.');
        }

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

    // Signing is also a one-way transition that posts a system message and
    // unlocks payment — same locking rationale as confirmMoveIn().
    $signed = DB::transaction(function () use ($reservation, $request) {
        $locked = Reservation::whereKey($reservation->getKey())
            ->lockForUpdate()
            ->firstOrFail();

        if (! $locked->signAgreement($request->ip())) {
            return false;
        }

        $locked->update(['tenant_tc_accepted_at' => now()]);
        $locked->postSystemMessage($locked->tenant->name . ' signed the rental agreement.');

        return true;
    });

    if (! $signed) {
        return back()->with('error', 'This agreement cannot be signed right now.');
    }

    return redirect()
        ->route('agreements.show', $reservation)
        ->with('success', 'Agreement signed. You can now proceed to payment.');
}
public function confirmMoveIn(Reservation $reservation)
{
    Gate::authorize('sign', $reservation);

    // This transition releases escrow, so the check-then-write has to be
    // atomic — a double-click could otherwise pass the status check twice
    // before either write commits and fire the release (and the system
    // message) twice. See RULES.md → Concurrency & State Transitions;
    // Admin\PaymentController::release is the reference implementation.
    $released = DB::transaction(function () use ($reservation) {
        $locked = Reservation::whereKey($reservation->getKey())
            ->lockForUpdate()
            ->firstOrFail();

        if (! $locked->confirmMoveIn()) {
            return null;
        }

        $landlord = $locked->property?->landlord;
        $landlordName = $landlord
            ? trim($landlord->first_name . ' ' . $landlord->last_name)
            : 'the landlord';

        $locked->postSystemMessage(
            $locked->tenant->name . " confirmed move-in. The unit is now occupied and the payment has been released to {$landlordName}."
        );

        return $locked->releasedPayment;
    });

    if (! $released) {
        return back()->with('error', 'Move-in cannot be confirmed right now. Your payment must be completed first.');
    }

    // After commit — a payout announced inside the transaction could still be
    // rolled back underneath the broadcast.
    PaymentStatusUpdated::dispatch($released->fresh());

    return redirect()
        ->route('agreements.show', $reservation)
        ->with('success', 'Move-in confirmed. Your payment has been released to the landlord.');
}
}