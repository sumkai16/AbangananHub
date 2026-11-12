<?php

namespace App\Http\Controllers\Landlord;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\LateFeeWaiver;
use App\Models\Notification;
use App\Models\Reservation;
use App\Models\TenantDetail;
use App\Services\RentLedger;
use App\Services\RentReminderNotifier;
use App\Support\BusinessTime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * One tenancy from the landlord's side: the contract page and its actions (tenant details, end, reminder, late
 * fee waiver). Its rent ledger page is LedgerController, under Payments.
 *
 * Serves walk-in and platform tenancies identically: both are an occupied unit
 * with rent falling due every month, and the escrow only ever covered the
 * initial payment. The difference between them is presentational — a walk-in is
 * badged as landlord-asserted — not structural.
 */
class TenancyController extends Controller
{
    /**
     * The contract page: who the tenant is, the lease, their details, the unit, and ending the tenancy. The money
     * (ledger, payments, deposit charges) is under Payments (LedgerController); this page only shows the balance
     * and links there.
     */
    public function show(Reservation $reservation)
    {
        Gate::authorize('viewTenancy', $reservation);

        // Eager-loaded up front: the balance strip reads the ledger summary, the header reads the tenant and
        // unit, and Model::preventLazyLoading is on in dev.
        $reservation->load([
            'tenant.tenantDetail',
            'property',
            'unit.media',
            'payments',
            'depositCharges',
            'conversation',
        ]);

        return view('landlord.tenancies.show', [
            'reservation' => $reservation,
            'summary' => RentLedger::for($reservation)->summary(),
        ]);
    }

    /**
     * Fill in or correct a walk-in tenant's emergency contact and ID. A walk-in cannot log in, so the
     * landlord who recorded them is the only one who can; an online tenant edits their own in Account
     * Settings, which is why the policy refuses everything but the landlord's own walk-ins.
     */
    public function updateTenantDetails(Request $request, Reservation $reservation)
    {
        Gate::authorize('editWalkInDetails', $reservation);

        $validated = $request->validate(TenantDetail::rules(false), TenantDetail::messages());

        if (TenantDetail::saveFor($reservation->tenant, $validated) === null) {
            return back()->with('warning', 'Nothing to save. Fill in at least one field.');
        }

        return redirect()
            ->route('landlord.tenancies.show', $reservation)
            ->with('success', 'Emergency contact and ID saved.');
    }

    /**
     * The tenant's full ID number, fetched only when the landlord taps Show, so it is never in the page
     * HTML. Each reveal is audited (without the number itself).
     */
    public function revealTenantId(Reservation $reservation): JsonResponse
    {
        Gate::authorize('viewTenantDetails', $reservation);

        $reservation->load('tenant.tenantDetail');
        $idNumber = $reservation->tenant?->tenantDetail?->id_number;

        abort_if(blank($idNumber), 404);

        AuditLog::record(
            'tenant_details.reveal_id',
            'Landlord viewed a tenant\'s ID number',
            $reservation,
            null,
            ['tenant_id' => $reservation->tenant_id],
        );

        return response()->json(['id_number' => $idNumber])->header('Cache-Control', 'no-store, private');
    }

    /**
     * Close out the tenancy and hand the unit back to the available pool.
     *
     * Locked and re-checked inside the transaction per RULES.md: this flips a
     * status and frees a unit as one consequential transition, and a
     * double-submit could otherwise release a unit a second tenancy had already
     * claimed in between.
     */
    public function endTenancy(Request $request, Reservation $reservation)
    {
        Gate::authorize('endTenancy', $reservation);

        $data = $request->validate([
            'move_out_date' => ['nullable', 'date', 'before_or_equal:' . BusinessTime::today()],
            // Optional: the landlord can note the deposit as returned now, or do it later from the lease.
            'deposit_returned_amount' => ['nullable', 'numeric', 'min:0'],
        ], [
            'move_out_date.before_or_equal' => 'A move-out date cannot be in the future.',
        ]);

        $depositNote = null;

        $ended = DB::transaction(function () use ($reservation, $data, &$depositNote) {
            $locked = Reservation::whereKey($reservation->getKey())
                ->with(['unit', 'property', 'tenant'])
                ->lockForUpdate()
                ->firstOrFail();

            $wasEnded = $locked->endTenancy(
                isset($data['move_out_date']) ? Carbon::parse($data['move_out_date']) : null
            );

            if ($wasEnded) {
                $this->notifyTenancyEnded($locked);

                if (filled($data['deposit_returned_amount'] ?? null)) {
                    $depositNote = $this->recordDepositReturn($locked, (float) $data['deposit_returned_amount']);
                }
            }

            return $wasEnded;
        });

        if (! $ended) {
            return back()->with('error', 'This tenancy is not currently active, so it cannot be ended.');
        }

        $message = 'Lease ended. The unit is available again and no new months are billed.'.($depositNote ? ' '.$depositNote : '');

        // The lease modal on the Leases page ends it in place and comes back to the list.
        if ($request->input('from') === 'contracts') {
            return redirect()->route('landlord.contracts.index', ['status' => 'ended'])->with('success', $message);
        }

        return redirect()
            ->route('landlord.tenancies.show', $reservation)
            ->with('success', $message);
    }

    /**
     * Note that the deposit went back to the tenant, after the lease has ended. The landlord's own record: no money
     * moves here. Allowed once, up to what is left of the deposit after any deposit charges.
     */
    public function returnDeposit(Request $request, Reservation $reservation)
    {
        Gate::authorize('viewTenancy', $reservation);
        abort_unless($reservation->rental_status === 'Completed', 404);

        $data = $request->validate(['deposit_returned_amount' => ['required', 'numeric', 'min:0']]);

        $note = DB::transaction(function () use ($reservation, $data) {
            $locked = Reservation::whereKey($reservation->getKey())->lockForUpdate()->firstOrFail();

            return $this->recordDepositReturn($locked, (float) $data['deposit_returned_amount']);
        });

        return redirect()
            ->route('landlord.contracts.index', ['status' => 'ended', 'open' => $reservation->reservation_id])
            ->with($note ? 'success' : 'warning', $note ?: 'The deposit was already marked as returned.');
    }

    /**
     * "Stop collecting": the landlord gives up on rent an ended tenant still owes. The months stay on the ledger,
     * but the balance leaves every total and no reminder is sent. Only for an ended lease that has a balance, and
     * reversible with resumeCollecting().
     */
    public function stopCollecting(Request $request, Reservation $reservation)
    {
        Gate::authorize('viewTenancy', $reservation);
        abort_unless($reservation->rental_status === 'Completed', 404);

        $result = DB::transaction(function () use ($reservation) {
            $locked = Reservation::whereKey($reservation->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->rent_written_off_at) {
                return null;
            }

            $owed = RentLedger::for($locked)->summary()['outstanding'];
            if ($owed <= 0) {
                return false;
            }

            $locked->forceFill(['rent_written_off_amount' => $owed, 'rent_written_off_at' => now()])->save();

            AuditLog::record(
                'rent.stop_collecting',
                'Landlord stopped collecting ₱'.number_format($owed, 2).' of unpaid rent on an ended lease',
                $locked,
                null,
                ['amount' => $owed],
            );

            return $owed;
        });

        return match (true) {
            $result === null => back()->with('warning', 'You had already stopped collecting this.'),
            $result === false => back()->with('warning', 'Nothing is owed on this lease.'),
            default => back()->with('success', 'Stopped collecting ₱'.number_format($result, 2).'. It no longer counts as owed. You can resume any time.'),
        };
    }

    /** Undo stopCollecting(): the balance counts as owed again. */
    public function resumeCollecting(Request $request, Reservation $reservation)
    {
        Gate::authorize('viewTenancy', $reservation);
        abort_unless($reservation->rental_status === 'Completed', 404);

        DB::transaction(function () use ($reservation) {
            $locked = Reservation::whereKey($reservation->getKey())->lockForUpdate()->firstOrFail();

            if (! $locked->rent_written_off_at) {
                return;
            }

            $locked->forceFill(['rent_written_off_amount' => null, 'rent_written_off_at' => null])->save();

            AuditLog::record('rent.resume_collecting', 'Landlord resumed collecting unpaid rent on an ended lease', $locked);
        });

        return back()->with('success', 'Collecting resumed. The balance counts as owed again.');
    }

    /** Writes the deposit-return note (once, capped at the deposit that is left). Null when it was already recorded. */
    private function recordDepositReturn(Reservation $reservation, float $amount): ?string
    {
        if ($reservation->deposit_returned_at) {
            return null;
        }

        $left = RentLedger::for($reservation)->summary()['depositRemaining'];
        $amount = round(min(max($amount, 0), $left), 2);

        $reservation->forceFill(['deposit_returned_amount' => $amount, 'deposit_returned_at' => now()])->save();

        return 'Deposit return of ₱'.number_format($amount, 2).' recorded.';
    }

    /**
     * endTenancy() itself dispatches nothing — it only flips the status and
     * frees the unit. This is the sole place a tenancy reaches 'Completed',
     * and until now nothing told either side the review/rating window had
     * opened: Review::canReview() and TenantRatingController both require
     * 'Occupied' OR 'Completed' (fixed alongside this), but neither party had
     * any reason to go looking once the tenancy page stopped being useful.
     *
     * A walk-in tenant has no account to notify — same guard TenancyController
     * ::remind() already applies for the same reason.
     */
    private function notifyTenancyEnded(Reservation $reservation): void
    {
        $property = $reservation->property;
        $tenant = $reservation->tenant;

        if ($tenant && ! $tenant->is_walk_in) {
            Notification::notify(
                $tenant->user_id,
                'review',
                'How was your stay?',
                'Your tenancy at ' . ($property->title ?? 'the property') . ' has ended. Leave a review to help other tenants.',
                $property ? route('properties.show', $property) : null,
            );
        }

        Notification::notify(
            $property?->landlord_id,
            'tenant_rating',
            'Rate your tenant',
            trim(($tenant->first_name ?? '') . ' ' . ($tenant->last_name ?? '')) . ' has moved out. Rate their tenancy.',
            route('landlord.reservations.rateTenant', $reservation),
        );
    }

    /**
     * Send the tenant an on-demand rent reminder now, instead of waiting for the
     * nightly job. Only reaches platform tenants — a walk-in has no account to
     * notify — so the button that posts here is disabled on walk-in cards; this
     * re-asserts it server-side rather than trusting the disabled attribute.
     *
     * Deliberately does not touch the rent_reminders idempotency guard: that
     * table paces the automatic job, and a landlord clicking "remind" is an
     * explicit act that shouldn't be suppressed by it (or suppress it).
     */
    public function remind(Reservation $reservation, RentReminderNotifier $notifier)
    {
        Gate::authorize('viewTenancy', $reservation);

        $reservation->load(['tenant', 'property', 'unit', 'payments']);
        $tenant = $reservation->tenant;

        if (! $tenant || $tenant->is_walk_in || $tenant->account_status !== 'active') {
            return $this->remindReply('warning', 'This tenant has no AbangananHub account to notify.');
        }

        $target = RentLedger::for($reservation)
            ->periods()
            ->firstWhere(fn ($p) => $p['status'] !== 'paid');

        if (! $target) {
            return $this->remindReply('warning', $tenant->first_name . ' has no outstanding rent to be reminded about.');
        }

        $notifier->sendToTenant($reservation, $target, Carbon::today());

        return $this->remindReply('success', 'Rent reminder sent to ' . $tenant->first_name . '.');
    }

    /**
     * Drop the late fee on one month. Only a month that really carries a fee can be waived, so the button cannot
     * be used to pre-waive a fee that has not happened. Idempotent: a second click finds the waiver and says so.
     * Audit-logged like a void, because it changes what a tenant owes.
     */
    public function waiveLateFee(Request $request, Reservation $reservation)
    {
        Gate::authorize('viewTenancy', $reservation);

        $data = $request->validate([
            'billing_period' => ['required', 'date_format:Y-m-d'],
            'reason'         => ['nullable', 'string', 'max:255'],
        ]);

        $month = Carbon::parse($data['billing_period'])->startOfMonth();
        $reservation->load(['payments', 'depositCharges', 'lateFeeWaivers', 'property']);

        $period = RentLedger::for($reservation)->periods()
            ->first(fn ($p) => $p['period']->isSameMonth($month));

        if (! $period || ($period['late_fee'] <= 0 && ! $period['late_fee_waived'])) {
            return back()->with('warning', 'There is no late fee on that month to waive.');
        }

        $waiver = LateFeeWaiver::firstOrCreate(
            ['reservation_id' => $reservation->reservation_id, 'billing_period' => $month->toDateString()],
            ['waived_by' => Auth::id(), 'reason' => $data['reason'] ?? null],
        );

        if ($waiver->wasRecentlyCreated) {
            AuditLog::record(
                'late_fee.waive',
                'Late fee waived for ' . $period['label'] . ' on ' . ($reservation->property->title ?? 'a tenancy'),
                $reservation,
                $data['reason'] ?? null,
                ['reservation_id' => $reservation->reservation_id, 'billing_period' => $month->toDateString(), 'amount' => $period['late_fee']],
            );
        }

        return back()->with('success', 'Late fee waived for ' . $period['label'] . '.');
    }

    /** The chat page posts this by fetch and wants JSON; the tenant cards post a form and want the flash. */
    private function remindReply(string $level, string $message)
    {
        if (request()->expectsJson()) {
            return response()->json(['ok' => $level === 'success', 'message' => $message], $level === 'success' ? 200 : 422);
        }

        return back()->with($level, $message);
    }
}
