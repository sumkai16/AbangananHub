<?php

namespace App\Http\Controllers\Landlord;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Notification;
use App\Models\Payment;
use App\Models\PaymentClaim;
use App\Models\Property;
use App\Models\Reservation;
use App\Services\LandlordPayouts;
use App\Services\RentLedger;
use App\Services\RentPaymentRecorder;
use App\Services\RentReminderNotifier;
use App\Support\BusinessTime;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

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
        'written_off' => ['dot' => 'bg-[#94A3B8]', 'text' => 'text-[#5B6A8E]', 'label' => 'Not collecting'],
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
        'written_off' => 5,
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
            // lateFeeWaivers and renewals are read by every ledger below; loading them here stops one query per tenancy each.
            ->with(['tenant', 'property:property_id,title', 'unit:unit_id,unit_label,rental_fee', 'payments', 'depositCharges', 'lateFeeWaivers', 'renewals']);

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
        // One payload holds every year, so zooming between years, months and days never asks the server again.
        $calendar = $view === 'calendar' ? $this->buildCalendar($rows, $request) : null;

        // Payment reports waiting for this landlord's answer, shown first on the page so cash and QR payments are never
        // missed. Every tenancy of this landlord, whatever the filters above say: an answer is owed either way.
        $pendingClaims = PaymentClaim::pending()
            ->whereHas('reservation.property', fn ($q) => $q->where('landlord_id', $landlordId))
            ->with(['reservation.tenant', 'reservation.property', 'reservation.unit'])
            ->oldest()
            ->get();

        return view('landlord.payments.index', [
            'pendingClaims' => $pendingClaims,
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
            // The Payouts tab, and the "waiting to be sent" line pointing at it, only while payouts apply.
            'awaitingPayout' => $request->user()->showsPayoutTools() ? LandlordPayouts::awaitingTotal($request->user()) : null,
        ]);
    }

    /**
     * "Remind all overdue": one in-app reminder to every tenant who is overdue right now. Same message the
     * per-row bell and the nightly job send (RentReminderNotifier). Walk-ins and inactive accounts have
     * nobody to notify, and a tenant who already got a rent reminder for this tenancy in the last 24 hours is
     * skipped, so a second click cannot nag anyone. The reply says how many went out and why others did not.
     */
    public function remindOverdue(Request $request, RentReminderNotifier $notifier)
    {
        $data = $request->validate(['property' => ['nullable', 'integer']]);
        $today = Carbon::today();
        $sent = 0;
        $alreadyToday = 0;
        $noAccount = 0;

        $reservations = Reservation::where('rental_status', 'Occupied')
            ->whereHas('property', fn ($q) => $q->where('landlord_id', Auth::id()))
            ->when(! empty($data['property']), fn ($q) => $q->where('property_id', $data['property']))
            ->with(['tenant', 'property:property_id,title,landlord_id', 'unit:unit_id,unit_label,rental_fee', 'payments', 'depositCharges', 'lateFeeWaivers'])
            ->get();

        foreach ($reservations as $reservation) {
            $ledger = RentLedger::for($reservation);

            if ($ledger->summary()['paymentStatus'] !== 'overdue') {
                continue;
            }

            $tenant = $reservation->tenant;

            if (! $tenant || $tenant->is_walk_in || $tenant->account_status !== 'active') {
                $noAccount++;

                continue;
            }

            $recently = Notification::where('user_id', $tenant->user_id)
                ->where('link', route('tenancy.show', $reservation))
                ->whereIn('title', ['Rent overdue', 'Rent due soon'])
                ->where('created_at', '>=', now()->subDay())
                ->exists();

            if ($recently) {
                $alreadyToday++;

                continue;
            }

            $period = $ledger->periods()->firstWhere(fn ($p) => $p['status'] !== 'paid');

            if ($period && $notifier->sendToTenant($reservation, $period, $today)) {
                $sent++;
            }
        }

        if ($sent === 0 && $alreadyToday === 0 && $noAccount === 0) {
            return back()->with('warning', 'Nobody is overdue right now.');
        }

        $notes = array_filter([
            $alreadyToday ? "{$alreadyToday} already reminded in the last 24 hours" : null,
            $noAccount ? "{$noAccount} without an app account (walk-ins), remind them in person" : null,
        ]);

        return back()->with(
            $sent > 0 ? 'success' : 'warning',
            ($sent > 0 ? "Reminder sent to {$sent} " . Str::plural('tenant', $sent) . '.' : 'No new reminders were sent.')
                . ($notes ? ' Skipped: ' . implode('; ', $notes) . '.' : '')
        );
    }

    /** The calendar opens on any year in this span (an address outside it falls back to now); the arrows stop here too. */
    private const CALENDAR_MIN_YEAR = 1990;

    private const CALENDAR_MAX_YEAR = 2100;

    /** How far past this month an open-ended tenancy's rent is projected on the calendar. */
    private const CALENDAR_PROJECTION_MONTHS = 12;

    /** The furthest an end-dated tenancy's rent is projected, so a far-off move-out date cannot bloat the page. */
    private const CALENDAR_PROJECTION_MAX_MONTHS = 60;

    /** Years shown after the current one by default, so a landlord can look ahead at leases that run longer. */
    private const CALENDAR_YEARS_AHEAD = 2;

    /** A year from the address, or null when it is missing, not four digits, or outside the span. */
    private function parseYear(mixed $value): ?int
    {
        if (is_string($value) && preg_match('/^\d{4}$/', $value)) {
            $year = (int) $value;

            if ($year >= self::CALENDAR_MIN_YEAR && $year <= self::CALENDAR_MAX_YEAR) {
                return $year;
            }
        }

        return null;
    }

    /** `?month=2026-09` → first of that month; anything else (or a month outside the span) falls back to this month. */
    private function parseMonth(mixed $value): Carbon
    {
        if (is_string($value) && preg_match('/^(\d{4})-(0[1-9]|1[0-2])$/', $value, $m) && $this->parseYear($m[1]) !== null) {
            return Carbon::createFromFormat('!Y-m', $value);
        }

        return now()->startOfMonth();
    }

    /**
     * Everything the zoomable calendar needs, for every year at once: the levels are all years, one year, one month
     * (the day grid), and the page switches between them in the browser (public/js/payments-calendar.js).
     *
     * A month the ledger has already billed (or that was prepaid into) comes straight from RentLedger::periods(), so
     * the calendar can never disagree with the ledger. A later month has no ledger row yet, so an occupied tenancy
     * still inside its term is projected as an upcoming due date, up to its move-out month, or a year ahead when it
     * is open-ended. The ledger runs once per tenancy, not once per month.
     *
     * The payload is kept small: a tenancy's name, unit and link are sent once under `people`, and each due date is
     * just [reservation id, status, expected, paid]. `months` holds the per-month totals the year views draw from.
     *
     * Only years with ledger data are in the payload (the first year rent fell due to the last year projected, and at least
     * two years ahead). Any other year or month the page can still open: the browser draws it empty, nothing is invented.
     *
     * @return array{today: string, level: string, year: int, start: int, month: string, years: list<int>, minYear: int, maxYear: int, months: array, days: array, people: array}
     */
    private function buildCalendar($rows, Request $request): array
    {
        $today = now();
        $thisMonth = $today->copy()->startOfMonth();
        $horizon = $thisMonth->copy()->addMonthsNoOverflow(self::CALENDAR_PROJECTION_MONTHS);
        $farthest = $thisMonth->copy()->addMonthsNoOverflow(self::CALENDAR_PROJECTION_MAX_MONTHS);

        $month = $this->parseMonth($request->query('month'));
        $level = match ($request->query('zoom')) {
            'years' => 'years',
            'year' => 'year',
            default => 'month',
        };
        $asked = $this->parseYear($request->query('year'));
        $year = $asked ?? $month->year;

        $people = [];
        $days = [];
        $months = [];
        $firstYear = $today->year;
        $lastYear = $today->year + self::CALENDAR_YEARS_AHEAD;

        $add = function (Reservation $reservation, Carbon $dueOn, string $status, float $expected, float $paid) use (&$people, &$days, &$months, &$lastYear) {
            $id = $reservation->reservation_id;

            if (! isset($people[$id])) {
                $tenant = $reservation->tenant;
                $people[$id] = [
                    'name'     => trim(($tenant->first_name ?? '') . ' ' . ($tenant->last_name ?? '')) ?: 'Unknown',
                    'unit'     => $reservation->unit->unit_label ?? '—',
                    'property' => $reservation->property->title ?? '',
                    'url'      => route('landlord.payments.ledger', $reservation),
                ];
            }

            $days[$dueOn->format('Y-m-d')][] = [$id, $status, round($expected, 2), round($paid, 2)];

            $lastYear = max($lastYear, $dueOn->year);
            $ym = $dueOn->format('Y-m');
            $totals = $months[$ym] ?? ['expected' => 0.0, 'collected' => 0.0, 'due' => 0, 'overdue' => 0, 'partial' => 0, 'paid' => 0, 'upcoming' => 0];
            $totals['expected'] += $expected;
            $totals['collected'] += $paid;
            $totals['due']++;
            // Paid ahead counts as paid here; the day view still tells the two apart.
            $totals[$status === 'paid_ahead' ? 'paid' : $status]++;
            $months[$ym] = $totals;
        };

        foreach ($rows as $row) {
            /** @var Reservation $reservation */
            $reservation = $row['reservation'];
            $covered = [];

            foreach (RentLedger::for($reservation)->periods() as $period) {
                $covered[$period['period']->format('Y-m')] = true;
                $firstYear = min($firstYear, $period['period']->year);

                $status = match (true) {
                    $period['is_future'] => $period['status'] === 'paid' ? 'paid_ahead' : 'upcoming',
                    $period['status'] === 'due' => 'upcoming',
                    default => $period['status'],
                };

                $add($reservation, $period['due_on'], $status, (float) $period['expected'], (float) $period['paid']);
            }

            $start = $reservation->tenancyStartDate();

            if ($reservation->rental_status !== 'Occupied' || ! $start) {
                continue;
            }

            $startMonth = $start->copy()->startOfMonth();
            $end = $reservation->target_move_out_date?->copy()->startOfMonth();
            $last = $end ?? $horizon;
            $last = $last->lt($farthest) ? $last : $farthest;

            for ($next = $thisMonth->copy()->addMonthNoOverflow(); $next->lte($last); $next->addMonthNoOverflow()) {
                if (isset($covered[$next->format('Y-m')]) || $next->lt($startMonth)) {
                    continue;
                }

                $add($reservation, $next->copy()->day($reservation->rentDueDay()), 'upcoming', (float) $reservation->rentFor($next), 0.0);
            }
        }

        foreach ($days as &$entries) {
            usort($entries, fn ($a, $b) => (self::STATUS_ORDER[$a[1]] ?? 99) <=> (self::STATUS_ORDER[$b[1]] ?? 99));
        }
        unset($entries);
        ksort($days);
        ksort($months);

        foreach ($months as &$totals) {
            $totals['expected'] = round($totals['expected'], 2);
            $totals['collected'] = round($totals['collected'], 2);
        }
        unset($totals);

        return [
            'today'   => $today->format('Y-m-d'),
            'level'   => $level,
            // The year level opens on this year; the all-years level opens on a window that starts at ?year=, or at the first year with data.
            'year'    => $year,
            'start'   => $level === 'years' && $asked ? $asked : $firstYear,
            'month'   => $month->format('Y-m'),
            'years'   => range($firstYear, $lastYear),
            'minYear' => self::CALENDAR_MIN_YEAR,
            'maxYear' => self::CALENDAR_MAX_YEAR,
            'months'  => $months,
            'days'    => $days,
            'people'  => $people,
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
            'paid_at'        => ['required', 'date', 'before_or_equal:' . BusinessTime::today()],
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

            $ended = $locked->rental_status === 'Completed';
            abort_unless(
                $locked->rental_status === 'Occupied' || ($ended && $locked->rent_written_off_at === null),
                409,
                'This tenancy is no longer active, so payments cannot be added to it.'
            );

            // An ended tenancy only takes rent that is still owed: nothing else, and never more than the balance,
            // or the excess would land in a month after move-out and read as "paid ahead".
            if ($ended) {
                $owed = RentLedger::for($locked)->summary()['outstanding'];

                if ($data['payment_type'] !== 'Monthly' || $owed <= 0) {
                    throw ValidationException::withMessages(['amount' => 'This rental has ended. Only unpaid rent can still be recorded.']);
                }
                if ((float) $data['amount'] > $owed + 0.001) {
                    throw ValidationException::withMessages(['amount' => 'This tenant owes ₱'.number_format($owed, 2).'. You cannot record more than that on an ended rental.']);
                }
            }

            return RentPaymentRecorder::record($locked, $data, Auth::id());
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
            abort_if($locked->payout_owed_at !== null, 409, 'This payment has already been paid out and can no longer be voided.');

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
        $payment->load(['reservation.property.landlord', 'reservation.tenant', 'reservation.unit', 'recorder', 'claim']);

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
            ->with(['tenant', 'property:property_id,title', 'unit:unit_id,unit_label,rental_fee', 'payments', 'depositCharges'])
            ->get();

        return response()->streamDownload(function () use ($reservations) {
            $handle = fopen('php://output', 'w');
            \App\Support\Csv::put($handle, [
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

                \App\Support\Csv::put($handle, [
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
