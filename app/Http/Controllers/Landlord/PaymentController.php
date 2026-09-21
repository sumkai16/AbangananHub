<?php

namespace App\Http\Controllers\Landlord;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Notification;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Reservation;
use App\Services\RentLedger;
use App\Support\RentPaymentAllocator;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Rent collection across the landlord's whole portfolio, and the endpoint that
 * records a payment against one tenancy.
 *
 * Every payment written here is landlord-asserted: it carries `recorded_by` and
 * lands on 'Paid', never 'Held'. Escrow exists to protect a tenant paying a
 * stranger through the platform before they have keys — money handed over in
 * person after move-in has nothing left to protect, and Admin\PaymentController
 * ::release() refuses anything that isn't 'Held', so these can never be
 * mistaken for escrowed funds.
 */
class PaymentController extends Controller
{
    /**
     * One map so the dot colour, the pill and the sort/filter values can never
     * drift apart. `paid_ahead` deliberately stays in the navy family —
     * DESIGN.md §6n: a future month covered in advance is credit, not debt,
     * and must never read amber or red.
     */
    private const STATUS_STYLES = [
        'overdue'    => ['dot' => 'bg-[#DC2626]', 'text' => 'text-[#DC2626]', 'label' => 'Overdue'],
        'partial'    => ['dot' => 'bg-[#B45309]', 'text' => 'text-[#B45309]', 'label' => 'Partial'],
        'upcoming'   => ['dot' => 'bg-[#94A3B8]', 'text' => 'text-[#5B6A8E]', 'label' => 'Upcoming'],
        'paid'       => ['dot' => 'bg-[#15803D]', 'text' => 'text-[#15803D]', 'label' => 'Paid'],
        'paid_ahead' => ['dot' => 'bg-[#060D26]', 'text' => 'text-[#060D26]', 'label' => 'Paid Ahead'],
    ];

    private const METHODS = ['Cash', 'GCash', 'Bank Transfer', 'Maya', 'Check', 'Other'];
    private const TYPES = ['Monthly', 'Deposit', 'Initial', 'Utility', 'Other'];

    /**
     * Row sort order, most urgent first. Kept as one map so the controller's
     * sort and the filter dropdown's options can't drift from each other.
     */
    private const STATUS_ORDER = [
        'overdue'    => 0,
        'partial'    => 1,
        'upcoming'   => 2,
        'paid'       => 3,
        'paid_ahead' => 4,
    ];

    /**
     * Collections overview: every tenancy with rent running, worst first.
     *
     * Sorted by payment status rather than by amount — the page exists to
     * answer "who do I need to chase", and a tenant who is a little overdue
     * still outranks one who is comfortably paid ahead, regardless of size.
     */
    public function index(Request $request)
    {
        $landlordId = Auth::id();

        $properties = Property::where('landlord_id', $landlordId)
            ->orderBy('title')
            ->get(['property_id', 'title']);

        $statusFilter = $request->query('status', 'all');
        $propertyId = $request->integer('property') ?: null;

        // Completed tenancies are included deliberately: a landlord still needs
        // to see what an ex-tenant left unpaid.
        $query = Reservation::whereIn('rental_status', ['Occupied', 'Completed'])
            ->whereHas('property', fn ($q) => $q->where('landlord_id', $landlordId))
            ->with(['tenant', 'property:property_id,title', 'unit:unit_id,unit_label,rental_fee', 'payments']);

        if ($propertyId && $properties->contains('property_id', $propertyId)) {
            $query->where('property_id', $propertyId);
        }

        if ($search = $request->input('search')) {
            $query->whereHas('tenant', function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Each row's status is derived, not stored, so the sort and the
        // status filter both have to happen after the ledger runs. A landlord
        // portfolio is tens of tenancies, not thousands — the alternative is
        // materialising the schedule into a table that can go stale.
        $allRows = $query->get()
            ->map(function (Reservation $reservation) {
                $ledger = RentLedger::for($reservation);
                $summary = $ledger->summary();

                return [
                    'reservation'   => $reservation,
                    'summary'       => $summary,
                    'paymentStatus' => $summary['paymentStatus'],
                ];
            });

        // The status dropdown offers only what actually occurs, taken before
        // the status filter runs so picking one never hides the others.
        $usedStatuses = $allRows->pluck('paymentStatus')->unique()->values()->all();
        $hasDueThisMonth = $allRows->contains(fn ($row) => $row['summary']['dueThisMonth'] > 0);

        $rows = $allRows
            ->when(
                $statusFilter === 'due_this_month',
                fn ($rows) => $rows->filter(fn ($row) => $row['summary']['dueThisMonth'] > 0),
                fn ($rows) => $statusFilter !== 'all' ? $rows->where('paymentStatus', $statusFilter) : $rows
            )
            ->sortBy(function ($row) {
                $tier = self::STATUS_ORDER[$row['paymentStatus']] ?? 99;
                $dueOn = $row['summary']['oldestUnpaid']['due_on'] ?? null;

                // A composite [tier, timestamp] pair — Collection::sortBy
                // compares same-shaped arrays element by element, so this
                // sorts by urgency tier first and, within a tier, oldest due
                // date first, in one pass.
                return [$tier, $dueOn?->timestamp ?? PHP_INT_MAX];
            })
            ->values();

        // Month-scoped: only this row's current-month period counts, so an
        // advance payment recorded this month into a future period does not
        // inflate what was actually collected against this month's rent.
        $totals = [
            'dueThisMonth'       => round($rows->sum(fn ($r) => $r['summary']['dueThisMonth']), 2),
            'collectedThisMonth' => round($rows->sum(fn ($r) => $r['summary']['collectedThisMonth']), 2),
            'outstanding'        => round($rows->sum(fn ($r) => $r['summary']['outstanding']), 2),
            'overdue'            => round($rows->sum(fn ($r) => $r['summary']['overdueAmount']), 2),
            'duePaymentCount'    => $rows->filter(fn ($r) => $r['summary']['dueThisMonth'] > 0)->count(),
            'unpaidCount'        => $rows->filter(fn ($r) => $r['summary']['outstanding'] > 0)->count(),
            'overdueCount'       => $rows->where('paymentStatus', 'overdue')->count(),
        ];

        // Null, not 0, when nothing is billed this month — a landlord with an
        // empty month should read "No dues this month", not a permanently
        // stuck 0%.
        $totals['collectedPercent'] = $totals['dueThisMonth'] > 0
            ? (int) round($totals['collectedThisMonth'] / $totals['dueThisMonth'] * 100)
            : null;

        $view = $request->query('view') === 'calendar' ? 'calendar' : 'list';
        $calendar = $view === 'calendar'
            ? $this->buildCalendar($rows, $this->parseMonth($request->query('month')))
            : null;

        // Month navigation fetches just the calendar so the page doesn't reload.
        if ($calendar && $request->ajax()) {
            return view('landlord.payments._calendar', [
                'calendar'      => $calendar,
                'paymentStyles' => self::STATUS_STYLES,
            ]);
        }

        return view('landlord.payments.index', [
            'paymentStyles' => self::STATUS_STYLES,
            'rows'         => $rows,
            'properties'   => $properties,
            'totals'       => $totals,
            'statusFilter' => $statusFilter,
            'propertyId'   => $propertyId,
            'usedStatuses' => $usedStatuses,
            'hasDueThisMonth' => $hasDueThisMonth,
            'view'         => $view,
            'calendar'    => $calendar,
        ]);
    }

    /** `?month=2026-09` → first of that month; anything else falls back to this month. */
    private function parseMonth(mixed $value): Carbon
    {
        if (is_string($value) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $value)) {
            return Carbon::createFromFormat('!Y-m', $value);
        }

        return now()->startOfMonth();
    }

    /**
     * Rent due dates for one month, keyed by 'Y-m-d'.
     *
     * A month the ledger has already billed (or that was prepaid into) comes
     * straight from RentLedger::periods(), so the calendar can never disagree
     * with the table. A later month has no ledger row yet — RentLedger stops at
     * the current month on purpose — so an occupied tenancy still inside its
     * term is projected as an upcoming due date; that projection is the whole
     * point of looking ahead on a calendar.
     */
    private function buildCalendar($rows, Carbon $month): array
    {
        $days = [];
        $expected = 0.0;
        $collected = 0.0;

        foreach ($rows as $row) {
            /** @var Reservation $reservation */
            $reservation = $row['reservation'];
            $period = RentLedger::for($reservation)->periods()
                ->first(fn ($p) => $p['period']->isSameMonth($month));

            if ($period) {
                $status = match (true) {
                    $period['is_future'] => $period['status'] === 'paid' ? 'paid_ahead' : 'upcoming',
                    $period['status'] === 'due' => 'upcoming',
                    default => $period['status'],
                };
                $dueOn = $period['due_on'];
                $entryExpected = $period['expected'];
                $entryPaid = $period['paid'];
            } else {
                $start = $reservation->tenancyStartDate();
                $end = $reservation->target_move_out_date;

                if (
                    $reservation->rental_status !== 'Occupied'
                    || ! $start
                    || $month->lte(now()->startOfMonth())
                    || $month->lt($start->copy()->startOfMonth())
                    || ($end && $month->gt($end->copy()->startOfMonth()))
                ) {
                    continue;
                }

                $status = 'upcoming';
                $dueOn = $month->copy()->day($reservation->rentDueDay());
                $entryExpected = $reservation->monthlyRent();
                $entryPaid = 0.0;
            }

            $tenant = $reservation->tenant;
            $days[$dueOn->format('Y-m-d')][] = [
                'name'     => trim(($tenant->first_name ?? '') . ' ' . ($tenant->last_name ?? '')) ?: 'Unknown',
                'unit'     => $reservation->unit->unit_label ?? '—',
                'property' => $reservation->property->title ?? '',
                'expected' => $entryExpected,
                'paid'     => $entryPaid,
                'balance'  => max(0, round($entryExpected - $entryPaid, 2)),
                'status'   => $status,
                'url'      => route('landlord.tenancies.show', $reservation),
            ];

            $expected += $entryExpected;
            $collected += $entryPaid;
        }

        foreach ($days as &$entries) {
            usort($entries, fn ($a, $b) => (self::STATUS_ORDER[$a['status']] ?? 99) <=> (self::STATUS_ORDER[$b['status']] ?? 99));
        }
        unset($entries);

        return [
            'month'     => $month,
            'days'      => $days,
            'expected'  => round($expected, 2),
            'collected' => round($collected, 2),
        ];
    }

    /**
     * Record money the landlord collected. For a Monthly payment that covers
     * more than the selected month, splits it across as many billing months
     * as it actually reaches — RentPaymentAllocator — rather than writing one
     * row that overpays the selected month and leaves later months unpaid.
     * Every other payment type is still a single row, exactly as posted.
     */
    public function store(Request $request, Reservation $reservation)
    {
        Gate::authorize('recordPayment', $reservation);

        $data = $request->validate([
            'payment_type'   => ['required', Rule::in(self::TYPES)],
            // Required for Monthly only: that is the type the ledger matches on
            // a month, and a monthly payment with no period would be collected
            // money that settles nothing and shows up nowhere.
            'billing_period' => ['required_if:payment_type,Monthly', 'nullable', 'date'],
            'amount'         => ['required', 'numeric', 'min:1', 'max:1000000'],
            'payment_method' => ['required', Rule::in(self::METHODS)],
            'paid_at'        => ['required', 'date', 'before_or_equal:today'],
            'reference_no'   => ['nullable', 'string', 'max:255'],
            'payment_notes'  => ['nullable', 'string', 'max:1000'],
            // Set only by the "void and record a correction" flow — every
            // other submit leaves this out.
            'replaces_payment_id' => ['nullable', 'integer'],
        ], [
            'billing_period.required_if' => 'Choose which month this rent payment covers.',
            'paid_at.before_or_equal'    => 'A payment cannot be recorded for a future date.',
        ]);

        $monthsCovered = DB::transaction(function () use ($data, $reservation) {
            // Re-read under a lock and re-assert the precondition the Gate
            // checked: this writes a money row, and the tenancy could have been
            // ended by another tab between the authorize() and here.
            $locked = Reservation::whereKey($reservation->getKey())->lockForUpdate()->firstOrFail();

            abort_unless(
                $locked->rental_status === 'Occupied',
                409,
                'This tenancy is no longer active, so payments cannot be added to it.'
            );

            // Scoped to this reservation and to an actually-voided row:
            // without the scope this is an IDOR that lets one landlord point
            // a payment at another landlord's row. `exists:` alone would not
            // catch that.
            $replaces = ! empty($data['replaces_payment_id'])
                ? Payment::where('payment_id', $data['replaces_payment_id'])
                    ->where('reservation_id', $locked->reservation_id)
                    ->where('status', 'Voided')
                    ->value('payment_id')
                : null;

            $shared = [
                'reservation_id'       => $locked->reservation_id,
                'payment_method'       => $data['payment_method'],
                'status'               => 'Paid',
                'paid_at'              => $data['paid_at'],
                'reference_no'         => $data['reference_no'] ?? null,
                'payment_notes'        => $data['payment_notes'] ?? null,
                'recorded_by'          => Auth::id(),
                'replaces_payment_id'  => $replaces,
            ];

            if ($data['payment_type'] !== 'Monthly') {
                Payment::create($shared + [
                    'payment_type'   => $data['payment_type'],
                    'billing_period' => null,
                    'amount'         => $data['amount'],
                ]);

                return 1;
            }

            $ledger = RentLedger::for($locked);
            $rows = RentPaymentAllocator::allocate(
                $ledger->unsettledPeriods(),
                Carbon::parse($data['billing_period'])->startOfMonth(),
                $locked->monthlyRent(),
                (float) $data['amount']
            );

            // The allocator can return nothing only when the chosen month and
            // every month after it happened to already be settled — record
            // the amount against the chosen month regardless, so a
            // landlord's entry is never silently dropped.
            if ($rows === []) {
                $rows = [[
                    'billing_period' => Carbon::parse($data['billing_period'])->startOfMonth(),
                    'amount'         => (float) $data['amount'],
                ]];
            }

            foreach ($rows as $row) {
                Payment::create($shared + [
                    'payment_type'   => 'Monthly',
                    'billing_period' => $row['billing_period'],
                    'amount'         => $row['amount'],
                ]);
            }

            return count($rows);
        });

        $message = $monthsCovered > 1
            ? "Payment recorded across {$monthsCovered} billing months."
            : 'Payment recorded.';

        return back()->with('success', $message);
    }

    /**
     * Strike a recorded payment from the ledger without deleting it.
     *
     * Nothing is erased: the row keeps its amount, month, date and reference
     * and gains who voided it, when, and why. 'Voided' is outside
     * RentLedger::SETTLED_STATUSES and AnalyticsController::EARNED_STATUSES,
     * so the month it settled reopens and the money stops counting as
     * revenue with no further change to either.
     *
     * Landlord-recorded rows only. A PayMongo-settled payment is evidence,
     * not an assertion — a landlord cannot declare that it did not happen.
     */
    public function void(Request $request, Payment $payment)
    {
        $payment->loadMissing(['reservation.property', 'reservation.tenant']);
        $reservation = $payment->reservation;

        abort_unless($reservation !== null, 404);
        Gate::authorize('voidPayment', $reservation);

        $data = $request->validate([
            'void_reason' => ['required', Rule::in(array_keys(Payment::VOID_REASONS))],
            'void_note'   => ['required_if:void_reason,other', 'nullable', 'string', 'max:255'],
            'correct'     => ['nullable', 'boolean'],
        ], [
            'void_note.required_if' => 'Say what went wrong with this payment.',
        ]);

        $voided = DB::transaction(function () use ($payment, $data) {
            // Money row: lock and re-assert every precondition inside the
            // transaction, per RULES.md → Concurrency. A double-submit hits
            // the status check on the second pass and 409s without logging
            // twice.
            $locked = Payment::whereKey($payment->getKey())->lockForUpdate()->firstOrFail();

            abort_unless($locked->isManuallyRecorded(), 403, 'Only a payment you recorded yourself can be voided.');
            abort_unless($locked->status === 'Paid', 409, 'This payment has already been voided, or was settled through the platform.');
            // Unreachable today — landlord-recorded rows never carry a payout
            // status — and here so that stays true if a future path changes it.
            abort_if($locked->payout_status !== null, 409, 'This payment has already been paid out and can no longer be voided.');

            $locked->update([
                'status'      => 'Voided',
                'voided_at'   => now(),
                'voided_by'   => Auth::id(),
                'void_reason' => $data['void_reason'],
                'void_note'   => $data['void_note'] ?? null,
            ]);

            AuditLog::record(
                'payment.void',
                '₱' . number_format((float) $locked->amount, 2) . ' payment voided on '
                    . ($locked->reservation->property->title ?? 'a tenancy'),
                $locked,
                Payment::VOID_REASONS[$data['void_reason']]
                    . (! empty($data['void_note']) ? ' — ' . $data['void_note'] : ''),
                [
                    'reservation_id' => $locked->reservation_id,
                    'amount'         => (float) $locked->amount,
                    'payment_type'   => $locked->payment_type,
                    'billing_period' => $locked->billing_period?->toDateString(),
                    'paid_at'        => $locked->paid_at?->toDateString(),
                ],
            );

            return $locked;
        });

        $this->notifyTenantOfVoid($voided, $reservation);

        $redirect = back()->with('success', 'Payment voided. The original entry stays on record.');

        // Hands the record-payment modal everything it needs to reopen
        // prefilled with the corrected figures, and to link the replacement
        // back to this row.
        if ($request->boolean('correct')) {
            $redirect->with('correct_payment', [
                'payment_id'     => $voided->payment_id,
                'payment_type'   => $voided->payment_type,
                'amount'         => (float) $voided->amount,
                'billing_period' => $voided->billing_period?->toDateString(),
            ]);
        }

        return $redirect;
    }

    /**
     * A void raises what the tenant appears to owe, on a page they can open.
     * A walk-in has no account to tell — the landlord's own record is the
     * only channel there, same limitation ProcessRentReminders already lives
     * with.
     */
    private function notifyTenantOfVoid(Payment $payment, Reservation $reservation): void
    {
        $tenant = $reservation->tenant;

        if (! $tenant || $tenant->is_walk_in) {
            return;
        }

        Notification::notify(
            $tenant->user_id,
            'payment',
            'A recorded payment was corrected',
            'Your landlord voided a ₱' . number_format((float) $payment->amount, 2)
                . ' entry on your rent ledger (' . strtolower($payment->voidReasonLabel()) . '). Check your balance.',
            route('tenancy.show', $reservation),
        );
    }

    /**
     * A printable acknowledgement for a payment this landlord recorded.
     *
     * Scoped to recorded payments on purpose — a PayMongo-settled payment has
     * its own receipt from PayMongo, and issuing a landlord-branded one for it
     * would misstate who took the money.
     */
    public function receipt(Payment $payment)
    {
        $payment->load(['reservation.property', 'reservation.tenant', 'reservation.unit', 'recorder']);

        $reservation = $payment->reservation;

        abort_unless($reservation !== null, 404);
        Gate::authorize('viewTenancy', $reservation);
        abort_unless($payment->isManuallyRecorded(), 404);
        // A voided payment settled nothing — printing a branded receipt for
        // it would misstate the ledger it no longer counts toward.
        abort_if($payment->isVoided(), 404);

        return view('landlord.payments.receipt', [
            'payment'     => $payment,
            'reservation' => $reservation,
            'business'    => Auth::user()->rentalBusiness,
        ]);
    }

    /**
     * CSV of the collections table. Streamed to match the existing landlord
     * exports (TenantController, OccupancyController).
     */
    public function export(Request $request)
    {
        $landlordId = Auth::id();
        $filename = 'abangananhub-rent-collections-' . now()->format('Y-m-d') . '.csv';

        $reservations = Reservation::whereIn('rental_status', ['Occupied', 'Completed'])
            ->whereHas('property', fn ($q) => $q->where('landlord_id', $landlordId))
            ->when($request->integer('property'), fn ($q, $id) => $q->where('property_id', $id))
            ->with(['tenant', 'property:property_id,title', 'unit:unit_id,unit_label,rental_fee', 'payments'])
            ->get();

        return response()->streamDownload(function () use ($reservations) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'Tenant', 'Type', 'Property', 'Unit', 'Status', 'Monthly Rent', 'Due Date',
                'Months Billed', 'Paid', 'Total Unpaid', 'Overdue Months', 'Overdue Amount',
            ]);

            foreach ($reservations as $reservation) {
                $summary = RentLedger::for($reservation)->summary();
                $tenant = $reservation->tenant;
                // Same row the table shows: the oldest unpaid month, or this
                // month's own settled row for a Paid/Paid Ahead tenancy — null
                // only for a tenancy nothing has been billed for yet.
                $row = $summary['oldestUnpaid'];
                $dueOn = $row['due_on'] ?? null;

                fputcsv($handle, [
                    $tenant ? trim($tenant->first_name . ' ' . $tenant->last_name) : '',
                    $tenant?->is_walk_in ? 'Walk-in' : 'Platform',
                    $reservation->property->title ?? '',
                    $reservation->unit->unit_label ?? '',
                    Str::headline($summary['paymentStatus']),
                    number_format($summary['monthlyRent'], 2, '.', ''),
                    $dueOn?->format('Y-m-d') ?? '',
                    $summary['periodCount'],
                    number_format($row['paid'] ?? 0, 2, '.', ''),
                    number_format($summary['outstanding'], 2, '.', ''),
                    $summary['overdueCount'],
                    number_format($summary['overdueAmount'], 2, '.', ''),
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
