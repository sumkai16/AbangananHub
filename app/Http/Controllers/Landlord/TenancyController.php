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
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * One tenancy and its rent ledger, from the landlord's side.
 *
 * Serves walk-in and platform tenancies identically: both are an occupied unit
 * with rent falling due every month, and the escrow only ever covered the
 * initial payment. The difference between them is presentational — a walk-in is
 * badged as landlord-asserted — not structural.
 */
class TenancyController extends Controller
{
    public function show(Reservation $reservation)
    {
        Gate::authorize('viewTenancy', $reservation);

        // Eager-loaded up front: the ledger reads payments, the header reads
        // the tenant and unit, and Model::preventLazyLoading is on in dev.
        $reservation->load([
            'tenant.tenantDetail',
            'property',
            'unit.media',
            'payments.recorder',
            'payments.voider',
            'payments.replacements',
            'depositCharges.charger',
            'depositCharges.voider',
            'conversation',
        ]);

        $ledger = RentLedger::for($reservation);

        return view('landlord.tenancies.show', [
            'reservation'         => $reservation,
            'ledger'              => $ledger,
            'periods'             => $ledger->periods(),
            'otherCharges'        => $ledger->otherCharges(),
            'summary'             => $ledger->summary(),
            'unsettledPeriods'    => $ledger->unsettledPeriods(),
            'monthlyTransactions' => $ledger->monthlyTransactions(),
            'voidedTransactions'  => $ledger->voidedTransactions(),
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
            'move_out_date' => ['nullable', 'date', 'before_or_equal:today'],
        ], [
            'move_out_date.before_or_equal' => 'A move-out date cannot be in the future.',
        ]);

        $ended = DB::transaction(function () use ($reservation, $data) {
            $locked = Reservation::whereKey($reservation->getKey())
                ->with(['unit', 'property', 'tenant'])
                ->lockForUpdate()
                ->firstOrFail();

            $wasEnded = $locked->endTenancy(
                isset($data['move_out_date']) ? Carbon::parse($data['move_out_date']) : null
            );

            if ($wasEnded) {
                $this->notifyTenancyEnded($locked);
            }

            return $wasEnded;
        });

        if (! $ended) {
            return back()->with('error', 'This tenancy is not currently active, so it cannot be ended.');
        }

        return redirect()
            ->route('landlord.tenancies.show', $reservation)
            ->with('success', 'Tenancy ended. The unit is available again and the ledger is closed.');
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
