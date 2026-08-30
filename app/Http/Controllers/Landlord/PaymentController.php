<?php

namespace App\Http\Controllers\Landlord;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Reservation;
use App\Services\RentLedger;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
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
    private const METHODS = ['Cash', 'GCash', 'Bank Transfer', 'Maya', 'Check', 'Other'];
    private const TYPES = ['Monthly', 'Deposit', 'Initial', 'Utility', 'Other'];

    /**
     * Collections overview: every tenancy with rent running, worst first.
     *
     * Sorted by what is owed rather than by date — the page exists to answer
     * "who do I need to chase", and a tenancy that is three months behind
     * matters more than one that started yesterday.
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
        //
        // Status priority mirrors RentLedger::statusLabel(): an overdue month
        // anywhere outranks everything else, then partial, then upcoming,
        // then paid, then paid ahead — the order a landlord actually wants to
        // work through the list in (spec: worst standing first).
        $statusRank = ['Overdue' => 0, 'Partial' => 1, 'Upcoming' => 2, 'Paid' => 3, 'Paid Ahead' => 4];

        $rows = $query->get()
            ->map(function (Reservation $reservation) {
                $ledger = RentLedger::for($reservation);
                $summary = $ledger->summary();
                $current = $ledger->currentPeriod();
                // The period this row's Due Date/Paid/Balance columns read
                // from: this month's obligation, or — for an ended tenancy
                // with nothing billed this month — whatever was left overdue.
                $reference = $current ?? $summary['oldestOverdue'];

                return [
                    'reservation'   => $reservation,
                    'summary'       => $summary,
                    'status'        => $ledger->statusLabel(),
                    'dueDate'       => $ledger->nextDueDate(),
                    'paid'          => $reference['paid'] ?? 0.0,
                    'balance'       => $reference['balance'] ?? 0.0,
                    'currentPeriod' => $current,
                ];
            })
            ->when(
                $statusFilter === 'due_this_month',
                fn ($rows) => $rows->filter(fn ($row) => ($row['currentPeriod']['balance'] ?? 0) > 0)
            )
            ->when(
                ! in_array($statusFilter, ['all', 'due_this_month'], true),
                fn ($rows) => $rows->filter(fn ($row) => strtolower(str_replace(' ', '_', $row['status'])) === $statusFilter)
            )
            ->sortBy([
                fn ($row) => $statusRank[$row['status']] ?? 99,
                fn ($row) => $row['dueDate']?->timestamp ?? PHP_INT_MAX,
            ])
            ->values();

        // Month-scoped: only this row's current-month period counts, so an
        // advance payment recorded this month into a future period does not
        // inflate what was actually collected against this month's rent.
        $dueThisMonth = round((float) $rows->sum(fn ($r) => $r['currentPeriod']['expected'] ?? 0), 2);
        $collectedThisMonth = round(
            (float) $rows->sum(fn ($r) => $r['currentPeriod'] ? min($r['currentPeriod']['paid'], $r['currentPeriod']['expected']) : 0),
            2
        );

        $totals = [
            'dueThisMonth'          => $dueThisMonth,
            'paymentsDueThisMonth'  => $rows->filter(fn ($r) => $r['currentPeriod'] !== null)->count(),
            'collectedThisMonth'    => $collectedThisMonth,
            'collectedThisMonthPct' => $dueThisMonth > 0 ? round(($collectedThisMonth / $dueThisMonth) * 100) : null,
            'outstanding'           => round($rows->sum(fn ($r) => $r['summary']['outstanding']), 2),
            'outstandingCount'      => $rows->filter(fn ($r) => $r['summary']['outstanding'] > 0)->count(),
            'overdue'               => round($rows->sum(fn ($r) => $r['summary']['overdueAmount']), 2),
            'overdueTenancies'      => $rows->where('status', 'Overdue')->count(),
        ];

        return view('landlord.payments.index', [
            'rows'         => $rows,
            'properties'   => $properties,
            'totals'       => $totals,
            'statusFilter' => $statusFilter,
            'propertyId'   => $propertyId,
        ]);
    }

    /**
     * Record money the landlord collected. Writes a Payment row and nothing
     * else — no status flips, no escrow, no unit changes.
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
        ], [
            'billing_period.required_if' => 'Choose which month this rent payment covers.',
            'paid_at.before_or_equal'    => 'A payment cannot be recorded for a future date.',
        ]);

        DB::transaction(function () use ($data, $reservation) {
            // Re-read under a lock and re-assert the precondition the Gate
            // checked: this writes a money row, and the tenancy could have been
            // ended by another tab between the authorize() and here.
            $locked = Reservation::whereKey($reservation->getKey())->lockForUpdate()->firstOrFail();

            abort_unless(
                $locked->rental_status === 'Occupied',
                409,
                'This tenancy is no longer active, so payments cannot be added to it.'
            );

            Payment::create([
                'reservation_id' => $locked->reservation_id,
                'payment_type'   => $data['payment_type'],
                'billing_period' => $data['payment_type'] === 'Monthly'
                    ? Carbon::parse($data['billing_period'])->startOfMonth()
                    : null,
                'amount'         => $data['amount'],
                'payment_method' => $data['payment_method'],
                'status'         => 'Paid',
                'paid_at'        => $data['paid_at'],
                'reference_no'   => $data['reference_no'] ?? null,
                'payment_notes'  => $data['payment_notes'] ?? null,
                'recorded_by'    => Auth::id(),
            ]);
        });

        return back()->with('success', 'Payment recorded.');
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
                'Tenant', 'Type', 'Property', 'Unit', 'Status', 'Monthly Rent',
                'Months Billed', 'Collected', 'Outstanding', 'Overdue Months', 'Overdue Amount',
            ]);

            foreach ($reservations as $reservation) {
                $summary = RentLedger::for($reservation)->summary();
                $tenant = $reservation->tenant;

                fputcsv($handle, [
                    $tenant ? trim($tenant->first_name . ' ' . $tenant->last_name) : '',
                    $tenant?->is_walk_in ? 'Walk-in' : 'Platform',
                    $reservation->property->title ?? '',
                    $reservation->unit->unit_label ?? '',
                    $reservation->rental_status,
                    number_format($summary['monthlyRent'], 2, '.', ''),
                    $summary['periodCount'],
                    number_format($summary['collected'], 2, '.', ''),
                    number_format($summary['outstanding'], 2, '.', ''),
                    $summary['overdueCount'],
                    number_format($summary['overdueAmount'], 2, '.', ''),
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
