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

        $pending = (clone $query)->where('payout_status', 'Pending Payout')
            ->latest('released_at')
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
