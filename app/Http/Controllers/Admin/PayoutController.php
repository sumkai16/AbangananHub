<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SendLandlordPayout;
use App\Models\AuditLog;
use App\Models\Payment;
use App\Models\Payout;
use App\Models\User;
use App\Models\UserPayoutAccount;
use App\Services\LandlordPayouts;
use App\Services\PaymongoTransfers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Money the platform owes landlords and the payouts that carry it (plans/online-and-direct-payments.md,
 * phase 4). Payouts normally go out by themselves (App\Jobs\SendLandlordPayout, then the hourly
 * `payouts:send`); this page shows what is waiting, what PayMongo is still processing and what failed, and
 * lets an admin retry, pause a landlord, or record a transfer they sent by hand.
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
                    // Why the automatic payout is not going out for this landlord, in words.
                    'blocked' => LandlordPayouts::blockedReason($landlord),
                ];
            })
            ->sortByDesc('total')
            ->values();

        $payouts = Payout::with(['landlord.payoutAccount', 'paidOutBy'])
            ->withCount('payments')
            ->latest('payout_id')
            ->take(30)
            ->get();

        // GCash accounts nobody has approved yet, newest first, each with a check of the name against the landlord's ID.
        $toApprove = UserPayoutAccount::with('user')
            ->whereNull('payout_approved_at')
            ->whereNotNull('gcash_number')
            ->whereNotNull('gcash_account_name')
            ->latest('updated_at')
            ->get()
            ->filter(fn (UserPayoutAccount $account) => $account->user !== null)
            ->map(fn (UserPayoutAccount $account) => [
                'account' => $account,
                'landlord' => $account->user,
                'matches' => LandlordPayouts::nameMatchesIdentity($account->user),
                'idNames' => LandlordPayouts::identityNames($account->user),
            ]);

        return view('admin.payouts.index', [
            'toApprove' => $toApprove,
            'pending' => $pending,
            'payouts' => $payouts,
            'automatic' => LandlordPayouts::automaticEnabled(),
            'configMissing' => PaymongoTransfers::missingConfig(),
        ]);
    }

    /**
     * Records that the admin sent everything pending for one landlord by hand as one lump GCash transfer. The
     * automatic path is the normal one; this is the fallback when it is not set up or a payout cannot go out.
     */
    public function markPaidOut(Request $request, User $user)
    {
        $data = $request->validate([
            'payout_reference' => ['required', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($data, $user) {
            $payout = LandlordPayouts::batch($user, 'Sent', [
                'payout_reference' => $data['payout_reference'],
                'paid_out_by' => auth()->id(),
                'paid_out_at' => now(),
                'resolved_at' => now(),
            ]);

            abort_if(! $payout, 409, 'Nothing is pending payout for this landlord.');

            $payments = $payout->payments()->pluck('payment_id');

            AuditLog::record(
                'payout.mark_paid_out',
                "Marked ₱{$payout->amount} across {$payments->count()} payment(s) as paid out to {$user->first_name} {$user->last_name}.",
                $user,
                null,
                [
                    'amount' => $payout->amount,
                    'payout_id' => $payout->payout_id,
                    'payment_ids' => $payments,
                    'payout_reference' => $data['payout_reference'],
                ],
            );
        });

        return back()->with('success', "Payout to {$user->first_name} {$user->last_name} recorded.");
    }

    /**
     * Try a payout again: a Failed one (after the GCash details were fixed), or a Processing one with no
     * PayMongo transfer id that the admin has confirmed in the PayMongo dashboard was never sent.
     */
    public function retry(Request $request, Payout $payout)
    {
        $retried = DB::transaction(function () use ($request, $payout) {
            $locked = Payout::whereKey($payout->payout_id)->lockForUpdate()->firstOrFail();

            if ($locked->isFailed()) {
                abort_if($locked->resolved_at !== null, 409, 'This payout was already retried.');
                $locked->update(['resolved_at' => now()]);
            } elseif ($locked->isProcessing()) {
                abort_if($locked->paymongo_transfer_id, 409, 'PayMongo has a transfer for this payout. Use Check status instead.');
                abort_unless($request->boolean('confirmed'), 422, 'Confirm that you checked the PayMongo dashboard first.');
                abort_unless(
                    PaymongoTransfers::releaseForRetry($locked, 'Cancelled by '.auth()->user()->first_name.' after checking PayMongo: nothing was sent.'),
                    409,
                    'This payout is no longer waiting.'
                );
            } else {
                abort(409, 'Only a failed or stuck payout can be retried.');
            }

            AuditLog::record(
                'payout.retry',
                "Retried payout #{$locked->payout_id} (₱{$locked->amount}).",
                $locked->landlord,
                null,
                ['payout_id' => $locked->payout_id],
            );

            return $locked;
        });

        SendLandlordPayout::dispatch($retried->landlord_id);

        $blocked = LandlordPayouts::blockedReason($retried->landlord);

        return back()->with($blocked ? 'warning' : 'success', $blocked
            ? "Queued, but it will wait: {$blocked}."
            : 'Payout queued to send again.');
    }

    /** Ask PayMongo how a Processing payout ended. */
    public function check(Payout $payout)
    {
        abort_unless($payout->isProcessing() && $payout->paymongo_transfer_id, 409, 'There is no PayMongo transfer to check.');

        PaymongoTransfers::refresh($payout);

        AuditLog::record('payout.check', "Checked the PayMongo status of payout #{$payout->payout_id}.", $payout->landlord, null, ['payout_id' => $payout->payout_id]);

        $status = $payout->fresh()->status;

        return back()->with('success', $status === 'Processing'
            ? 'PayMongo has not finished this transfer yet.'
            : "Payout #{$payout->payout_id} is now {$status}.");
    }

    /**
     * An admin agrees money may be sent to this landlord's GCash account. Required before the first payout, and
     * again whenever the number or name changes. The page shows whether the name matches the landlord's ID.
     */
    public function approveAccount(User $user)
    {
        DB::transaction(function () use ($user) {
            $account = UserPayoutAccount::whereKey($user->user_id)->lockForUpdate()->first();

            abort_if(! $account || blank($account->gcash_number) || blank($account->gcash_account_name), 409, 'This landlord has no GCash account to approve.');
            abort_if($account->isApproved(), 409, 'This GCash account is already approved.');

            $account->update(['payout_approved_at' => now(), 'payout_approved_by' => auth()->id()]);

            $matches = LandlordPayouts::nameMatchesIdentity($user, $account->gcash_account_name);

            AuditLog::record(
                'payout_account.approve',
                "Approved the GCash account of {$user->first_name} {$user->last_name} for payouts.",
                $user,
                null,
                ['name_matches_id' => $matches, 'gcash_last4' => substr((string) $account->gcash_number, -4)],
            );
        });

        // Whatever was waiting on the approval can go out now.
        if (LandlordPayouts::hasMoneyOwed($user)) {
            SendLandlordPayout::dispatch($user->user_id);
        }

        return back()->with('success', "{$user->first_name} {$user->last_name}'s GCash account is approved for payouts.");
    }

    public function pause(User $user)
    {
        $this->setPaused($user, true);

        return back()->with('success', "Payouts to {$user->first_name} {$user->last_name} are paused.");
    }

    public function resume(User $user)
    {
        $this->setPaused($user, false);

        return back()->with('success', "Payouts to {$user->first_name} {$user->last_name} resume on the next run.");
    }

    private function setPaused(User $user, bool $paused): void
    {
        DB::transaction(function () use ($user, $paused) {
            $account = UserPayoutAccount::whereKey($user->user_id)->lockForUpdate()->first();

            abort_if(! $account, 409, 'This landlord has no payout details, so there is nothing to pause.');
            abort_if($paused === $account->isPaused(), 409, $paused ? 'Already paused.' : 'Not paused.');

            $account->update([
                'paused_at' => $paused ? now() : null,
                'paused_by' => $paused ? auth()->id() : null,
            ]);

            AuditLog::record(
                $paused ? 'payout.pause' : 'payout.resume',
                ($paused ? 'Paused' : 'Resumed')." payouts to {$user->first_name} {$user->last_name}.",
                $user,
            );
        });
    }
}
