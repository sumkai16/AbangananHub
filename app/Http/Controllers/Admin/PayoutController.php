<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Payment;
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
        $pending = Payment::with(['reservation.property.landlord'])
            ->where('payout_status', 'Pending Payout')
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

        $recentlyPaidOut = Payment::with(['reservation.property.landlord', 'payoutRecorder'])
            ->where('payout_status', 'Paid Out')
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
        ]);

        DB::transaction(function () use ($data, $user) {
            $payments = Payment::whereHas(
                'reservation.property',
                fn ($q) => $q->where('landlord_id', $user->user_id)
            )
                ->where('payout_status', 'Pending Payout')
                ->lockForUpdate()
                ->get();

            abort_if($payments->isEmpty(), 409, 'Nothing is pending payout for this landlord.');

            $total = $payments->sum('amount');

            foreach ($payments as $payment) {
                $payment->update([
                    'payout_status' => 'Paid Out',
                    'paid_out_at' => now(),
                    'paid_out_by' => auth()->id(),
                    'payout_reference' => $data['payout_reference'],
                ]);
            }

            AuditLog::record(
                'payout.mark_paid_out',
                "Marked ₱{$total} across {$payments->count()} payment(s) as paid out to {$user->first_name} {$user->last_name}.",
                $user,
                null,
                [
                    'amount' => $total,
                    'payment_ids' => $payments->pluck('payment_id'),
                    'payout_reference' => $data['payout_reference'],
                ],
            );
        });

        return back()->with('success', "Payout to {$user->first_name} {$user->last_name} recorded.");
    }
}
