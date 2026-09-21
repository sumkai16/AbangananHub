<?php

namespace App\Http\Controllers\Landlord;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Support\Facades\Auth;

/**
 * A landlord's own read-only view of what AbangananHub owes them and what it
 * has already sent — the other half of the manual-payout queue an admin works
 * from. See docs/specs/2026-07-26-landlord-payout-design.md.
 */
class PayoutController extends Controller
{
    public function index()
    {
        $landlordId = Auth::id();

        $query = Payment::whereHas(
            'reservation.property',
            fn ($q) => $q->where('landlord_id', $landlordId)
        )->with(['reservation.tenant', 'reservation.property:property_id,title', 'reservation.unit:unit_id,unit_label']);

        // Rent payments never get `released_at` (only the escrowed Initial
        // payment does — see Reservation::release()), so ordering by that
        // column alone leaves rent rows NULL and unsorted relative to each
        // other, out of step with the "Settled" column the view actually
        // displays (released_at ?? paid_at).
        $pending = (clone $query)->where('payout_status', 'Pending Payout')
            ->orderByRaw('COALESCE(released_at, paid_at) DESC')
            ->get();

        $paidOutTotal = (clone $query)->where('payout_status', 'Paid Out')->sum('amount');

        $paidOut = (clone $query)->where('payout_status', 'Paid Out')
            ->latest('paid_out_at')
            ->paginate(15);

        return view('landlord.payouts.index', [
            'pending' => $pending,
            'pendingTotal' => $pending->sum('amount'),
            'paidOut' => $paidOut,
            'paidOutTotal' => $paidOutTotal,
            'hasPayoutDestination' => Auth::user()->hasPayoutDestination(),
        ]);
    }
}
