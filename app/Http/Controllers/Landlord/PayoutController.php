<?php

namespace App\Http\Controllers\Landlord;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Payout;
use App\Models\UserPayoutAccount;
use App\Services\LandlordPayouts;
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
        )->with(['reservation.tenant', 'reservation.property:property_id,title', 'reservation.unit:unit_id,unit_label', 'payout']);

        // Rent payments never get `released_at` (only the escrowed Initial
        // payment does — see Reservation::release()), so ordering by that
        // column alone leaves rent rows NULL and unsorted relative to each
        // other, out of step with the "Settled" column the view actually
        // displays (released_at ?? paid_at).
        // Owed and not sent yet: still waiting, or with PayMongo (the row says "Sending").
        $pending = (clone $query)->awaitingPayout()
            ->orderByRaw('COALESCE(released_at, paid_at) DESC')
            ->get();

        $paidOutTotal = (clone $query)->paidOut()->sum('amount');

        $paidOut = (clone $query)->paidOut()
            ->with('payout')
            ->orderByDesc(Payout::select('paid_out_at')->whereColumn('payouts.payout_id', 'payments.payout_id'))
            ->paginate(15);

        return view('landlord.payouts.index', [
            'pending' => $pending,
            'pendingTotal' => $pending->sum('amount'),
            'paidOut' => $paidOut,
            'paidOutTotal' => $paidOutTotal,
            'hasPayoutDestination' => Auth::user()->hasPayoutDestination(),
            // Why nothing is going out right now, in the landlord's words, or null.
            'holdNotice' => $this->holdNotice(),
        ]);
    }

    private function holdNotice(): ?string
    {
        $account = UserPayoutAccount::find(Auth::id());

        if ($account?->isPaused()) {
            return 'Payouts to you are paused. Contact AbangananHub if you are not sure why.';
        }

        if ($account?->isOnHold()) {
            return 'Payouts resume on '.$account->payouts_hold_until->format('M j, Y \a\t g:i A').' because you changed your GCash details. What you are owed stays safe.';
        }

        if ($account && filled($account->gcash_number) && filled($account->gcash_account_name) && ! $account->isApproved()) {
            return 'An administrator has to approve your GCash account before the first payout. What you are owed stays safe and is sent once it is approved.';
        }

        if ($account && ! LandlordPayouts::automaticEnabled()) {
            return 'AbangananHub is paying landlords by hand for now. What you are owed stays safe and an administrator sends it to your GCash.';
        }

        return null;
    }
}
