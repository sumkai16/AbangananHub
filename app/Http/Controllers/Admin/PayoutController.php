<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Payment;
use App\Models\Payout;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Money the platform owes landlords, grouped by landlord — the manual-payout
 * queue described in docs/specs/2026-07-26-landlord-payout-design.md. There is
 * no disbursement API here: an admin sends the GCash transfer themselves and
 * this only records that it happened.
 */
class PayoutController extends Controller
{
    public function index()
    {
        $pending = Payment::with(['reservation.property.landlord.payoutAccount'])
            ->pendingPayout()
            ->get()
            ->groupBy(fn (Payment $payment) => $payment->reservation?->property?->landlord_id)
            ->filter(fn ($rows, $landlordId) => $landlordId !== null)
            ->map(function ($rows, $landlordId) {
                $landlord = $rows->first()->reservation->property->landlord;

                return [
                    'landlord' => $landlord,
                    'payments' => $rows,
                    'total' => $rows->sum('amount'),
                ];
            })
            ->sortByDesc('total')
            ->values();

        $recentlyPaidOut = Payout::with(['landlord', 'paidOutBy'])
            ->withCount('payments')
            ->latest('paid_out_at')
            ->take(25)
            ->get();

        return view('admin.payouts.index', [
            'pending' => $pending,
            'recentlyPaidOut' => $recentlyPaidOut,
        ]);
    }

    /**
     * Marks every currently-pending payment for one landlord as paid out in a
     * single batch — the admin has just sent one lump GCash transfer covering
     * all of it, not per-payment amounts.
     */
    public function markPaidOut(Request $request, User $user)
    {
        $data = $request->validate([
            'payout_reference' => ['required', 'string', 'max:255'],
            // The total the admin was shown. Required: a payout is only recorded for the amount that was actually sent.
            'expected_total' => ['required', 'numeric', 'min:0'],
        ]);

        DB::transaction(function () use ($data, $user) {
            $payments = Payment::whereHas(
                'reservation.property',
                fn ($q) => $q->where('landlord_id', $user->user_id)
            )
                ->pendingPayout()
                ->lockForUpdate()
                ->get();

            abort_if($payments->isEmpty(), 409, 'Nothing is pending payout for this landlord.');

            $total = $payments->sum('amount');

            // A payment released while the admin was sending GCash would be marked paid out but never sent.
            abort_if(
                Payment::toCentavos($data['expected_total']) !== Payment::toCentavos($total),
                409,
                'The amount owed changed while you were on this page. Reload and check the total before sending.'
            );

            // The GCash details are copied onto the payout: the record must keep saying where this
            // transfer went even after the landlord changes their payout account.
            $account = $user->payoutAccount;

            $payout = Payout::create([
                'landlord_id' => $user->user_id,
                'amount' => $total,
                'gcash_number' => $account?->gcash_number,
                'gcash_account_name' => $account?->gcash_account_name,
                'payout_reference' => $data['payout_reference'],
                'paid_out_by' => auth()->id(),
                'paid_out_at' => now(),
            ]);

            Payment::whereIn('payment_id', $payments->pluck('payment_id'))->update(['payout_id' => $payout->payout_id]);

            AuditLog::record(
                'payout.mark_paid_out',
                "Marked ₱{$total} across {$payments->count()} payment(s) as paid out to {$user->first_name} {$user->last_name}.",
                $user,
                null,
                [
                    'amount' => $total,
                    'payout_id' => $payout->payout_id,
                    'payment_ids' => $payments->pluck('payment_id'),
                    'payout_reference' => $data['payout_reference'],
                ],
            );
        });

        return back()->with('success', "Payout to {$user->first_name} {$user->last_name} recorded.");
    }
}
