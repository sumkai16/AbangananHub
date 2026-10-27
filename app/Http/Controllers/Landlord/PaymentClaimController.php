<?php

namespace App\Http\Controllers\Landlord;

use App\Http\Controllers\Controller;
use App\Models\PaymentClaim;
use App\Models\Reservation;
use App\Services\PaymentClaims;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** The landlord's side of direct payments: confirm or reject what a tenant reports, or record move-in money. */
class PaymentClaimController extends Controller
{
    public function confirm(Request $request, PaymentClaim $claim)
    {
        Gate::authorize('decidePayment', $claim->reservation);

        PaymentClaims::confirm($claim, $request->user());

        return back()->with('success', 'Payment confirmed. It now counts as paid.');
    }

    public function reject(Request $request, PaymentClaim $claim)
    {
        Gate::authorize('decidePayment', $claim->reservation);

        $data = $request->validate([
            'rejection_reason' => ['required', 'string', 'min:3', 'max:255'],
        ], [
            'rejection_reason.required' => 'Tell your tenant why, for example "No payment with that reference in my account."',
        ]);

        PaymentClaims::reject($claim, $request->user(), $data['rejection_reason']);

        return back()->with('success', 'Payment report rejected. Your tenant was told why.');
    }

    /** Cash at the door, or a transfer the landlord already saw, recorded before the tenant reports anything. */
    public function recordMoveIn(Request $request, Reservation $reservation)
    {
        Gate::authorize('recordMoveInPayment', $reservation);

        $data = $request->validate([
            'initial_amount' => ['required', 'numeric', 'min:1', 'max:1000000'],
            'payment_method' => ['required', Rule::in(['Cash', 'GCash', 'Maya', 'GoTyme', 'Bank Transfer', 'Other'])],
            'payment_date' => ['required', 'date', 'before_or_equal:today'],
            'reference_no' => ['nullable', 'string', 'max:100'],
        ]);

        PaymentClaims::recordMoveIn($reservation, $request->user(), $data);

        return back()->with('success', 'Move-in payment recorded.');
    }

    /** Either side can confirm move-in once the direct payment is settled; see ReservationPolicy::confirmDirectMoveIn. */
    public function confirmMoveIn(Request $request, Reservation $reservation)
    {
        Gate::authorize('confirmDirectMoveIn', $reservation);

        $byTenant = $request->user()->user_id === $reservation->tenant_id;

        $done = DB::transaction(function () use ($reservation, $byTenant) {
            $locked = Reservation::whereKey($reservation->getKey())->lockForUpdate()->firstOrFail();

            if (! $locked->markOccupied($byTenant)) {
                return false;
            }

            $who = $byTenant ? $locked->tenant->name : 'The landlord';
            $locked->postSystemMessage("{$who} confirmed move-in. The unit is now occupied.");

            return true;
        });

        if (! $done) {
            return back()->with('error', 'Move-in cannot be confirmed right now.');
        }

        return back()->with('success', 'Move-in confirmed.');
    }
}
