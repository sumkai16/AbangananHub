<?php

namespace App\Http\Controllers\Landlord;

use App\Http\Controllers\Controller;
use App\Models\DepositCharge;
use App\Models\OccupancyActivity;
use App\Models\Payment;
use App\Models\Property;
use App\Models\PropertyUnit;
use App\Models\Reservation;
use App\Services\OccupancyRateCalculator;
use App\Services\RentLedger;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class AnalyticsController extends Controller
{
    /**
     * Revenue counts money the tenant has actually parted with: 'Paid' is a
     * payment the landlord recorded as collected offline (a walk-in's deposit,
     * a monthly rent handed over in person), 'Held' is paid and sitting in
     * escrow, 'Released' has reached the landlord. All three are money that has
     * arrived; 'Pending' is a checkout session that may never complete, so it
     * is deliberately not revenue.
     *
     * 'Paid' was added when the rent ledger shipped — without it every
     * landlord-recorded payment was invisible here, so a landlord collecting
     * rent by hand would see their revenue chart flatline.
     *
     * A security deposit is excluded even when paid: it is still the
     * tenant's money, owed back at move-out. It shows on its own "Deposits
     * held" card instead. Only the part a landlord keeps through a
     * deposit_charges row (damage, cleaning, ...) becomes revenue, dated by
     * charged_at — the day the landlord actually kept it.
     */
    private const EARNED_STATUSES = ['Paid', 'Held', 'Released'];

    private const NON_REVENUE_TYPES = ['Deposit'];

    public function index(Request $request)
    {
        [$from, $to] = $this->resolveRange($request);

        $landlordId = Auth::id();
        $properties = Property::where('landlord_id', $landlordId)
            ->orderBy('title')
            ->get();
        $propertyIds = $properties->pluck('property_id');

        $units = PropertyUnit::whereIn('property_id', $propertyIds)->get();

        // ── Headline counts ──────────────────────────────────
        $occupiedUnits = $units->where('availability_status', 'Occupied')->count();
        $reservedUnits = $units->where('availability_status', 'Reserved')->count();
        $availableUnits = $units->where('availability_status', 'Available')->count();
        $maintenanceUnits = $units->where('availability_status', 'Maintenance')->count();

        // One grouped query for the window; the headline total and every
        // per-property figure below are read out of it.
        $revenueByPropertyId = $this->revenueByProperty($propertyIds, $from, $to);
        $revenue = (float) $revenueByPropertyId->sum();
        $activeReservations = $this->activeReservationCount($propertyIds);

        // Previous window of equal length, for the month-over-month deltas.
        $spanDays = $from->diffInDays($to) + 1;
        $prevTo = (clone $from)->subDay()->endOfDay();
        $prevFrom = (clone $prevTo)->subDays($spanDays - 1)->startOfDay();

        $stats = [
            'properties'  => $properties->count(),
            'units'       => $units->count(),
            'occupied'    => $occupiedUnits,
            'revenue'     => $revenue,
            'reservations' => $activeReservations,
            'depositsHeld' => $this->depositsHeld($propertyIds),
            'occupancyRate' => OccupancyRateCalculator::forLandlord($landlordId),
            'revenueDelta' => $this->percentChange(
                $this->revenueBetween($propertyIds, $prevFrom, $prevTo),
                $revenue
            ),
            'reservationsDelta' => $this->percentChange(
                Reservation::whereIn('property_id', $propertyIds)
                    ->whereBetween('created_at', [$prevFrom, $prevTo])->count(),
                Reservation::whereIn('property_id', $propertyIds)
                    ->whereBetween('created_at', [$from, $to])->count()
            ),
        ];

        // ── Occupancy overview (donut) ───────────────────────
        $occupancyBreakdown = [
            ['label' => 'Occupied',    'count' => $occupiedUnits,    'color' => '#22C55E'],
            ['label' => 'Reserved',    'count' => $reservedUnits,    'color' => '#FBBF24'],
            ['label' => 'Available',   'count' => $availableUnits,   'color' => '#FF8A66'],
            ['label' => 'Maintenance', 'count' => $maintenanceUnits, 'color' => '#94A3B8'],
        ];

        // ── Revenue over the last 6 months (line) ────────────
        $monthlyRevenue = $this->monthlyRevenue($propertyIds, 6);
        $revenueTrend = collect(range(5, 0))->map(function ($monthsAgo) use ($monthlyRevenue) {
            $start = now()->startOfMonth()->subMonths($monthsAgo);

            return [
                'label' => $start->format('M'),
                'value' => $monthlyRevenue[$start->format('Y-m')] ?? 0.0,
            ];
        })->values();

        // ── Per-property revenue + occupancy ─────────────────
        $perProperty = $properties->map(function (Property $property) use ($units, $revenueByPropertyId) {
            $propertyUnits = $units->where('property_id', $property->property_id);
            $total = $propertyUnits->count();
            $occupied = $propertyUnits->where('availability_status', 'Occupied')->count();

            return [
                'property_id' => $property->property_id,
                'title'       => $property->title,
                'total'       => $total,
                'occupied'    => $occupied,
                'reserved'    => $propertyUnits->where('availability_status', 'Reserved')->count(),
                'available'   => $propertyUnits->where('availability_status', 'Available')->count(),
                'rate'        => $total > 0 ? round(($occupied / $total) * 100, 1) : 0.0,
                'revenue'     => (float) ($revenueByPropertyId[$property->property_id] ?? 0),
            ];
        })->sortByDesc('revenue')->values();

        // Top 4 by revenue, everything else folded into "Others" so the donut
        // stays readable when a landlord has a long portfolio.
        $revenueByProperty = $perProperty->where('revenue', '>', 0)->values();
        $topSlices = $revenueByProperty->take(4);
        $othersTotal = $revenueByProperty->skip(4)->sum('revenue');

        // ── Reservations by status (donut) ───────────────────
        $statusCounts = Reservation::whereIn('property_id', $propertyIds)
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('rental_status, COUNT(*) as aggregate')
            ->groupBy('rental_status')
            ->pluck('aggregate', 'rental_status');

        $reservationBreakdown = [
            // 'Reserved' (a walk-in awaiting its move-in date) folds in here —
            // it's "not yet occupied" just like the platform-pipeline stages,
            // and volume is low enough that it doesn't need its own slice.
            ['label' => 'In progress', 'count' => (int) $statusCounts->only(['Inquiry', 'Under Negotiation', 'Pending Rental Agreement', 'Rental Agreement Signed', 'Reserved'])->sum(), 'color' => '#FBBF24'],
            ['label' => 'Occupied',    'count' => (int) ($statusCounts['Occupied'] ?? 0),  'color' => '#22C55E'],
            ['label' => 'Cancelled',   'count' => (int) ($statusCounts['Cancelled'] ?? 0), 'color' => '#94A3B8'],
            ['label' => 'Rejected',    'count' => (int) ($statusCounts['Rejected'] ?? 0),  'color' => '#EF4444'],
        ];

        // ── Vacancy Watch (point-in-time, not range-filtered) ──
        // Moved here when landlord/occupancy was deleted: it duplicated this
        // page's occupancy numbers, but nothing here answered "which empty
        // units are costing me money, and how much". No extra query — $units
        // is already loaded in full above.
        $vacantUnits = $units->where('availability_status', 'Available')->values();

        // A unit that has never been let has no vacated_at, so it has been empty
        // since it was created — a longer, more urgent vacancy than a recently
        // ended tenancy, not an absent one.
        $vacancy = [
            'count'     => $vacantUnits->count(),
            'idle_rent' => (float) $vacantUnits->sum('rental_fee'),
            'units'     => $vacantUnits
                ->map(function (PropertyUnit $unit) use ($properties) {
                    $since = $unit->vacated_at ?? $unit->created_at;

                    return [
                        'label'     => $unit->unit_label,
                        'property'  => $properties->firstWhere('property_id', $unit->property_id)?->title,
                        'days'      => $since ? (int) $since->startOfDay()->diffInDays(now()->startOfDay()) : null,
                        'rent'      => (float) $unit->rental_fee,
                        'never_let' => $unit->vacated_at === null,
                        'edit_url'  => route('landlord.properties.units.edit', [$unit->property_id, $unit->unit_id]),
                    ];
                })
                ->sortByDesc('days')
                ->take(5)
                ->values(),
        ];

        // ── Recent Activity (point-in-time) ──────────────────
        // The only web reader of occupancy_activities. Without it that table
        // would be write-only, which is a state this codebase already carries
        // once (occupancy_snapshots) and should not carry twice by accident.
        $recentActivities = OccupancyActivity::with([
            'unit:unit_id,unit_label',
            'property:property_id,title',
            'actor:user_id,first_name,last_name',
            'tenant:user_id,first_name,last_name',
        ])
            ->where('landlord_id', $landlordId)
            ->latest('activity_id')
            ->limit(8)
            ->get();

        return view('landlord.analytics.index', [
            'from'                 => $from,
            'to'                   => $to,
            'rangeKey'             => $request->query('range', 'this_month'),
            'stats'                => $stats,
            'occupancyBreakdown'   => $occupancyBreakdown,
            'revenueTrend'         => $revenueTrend,
            'perProperty'          => $perProperty,
            'topSlices'            => $topSlices,
            'othersTotal'          => $othersTotal,
            'reservationBreakdown' => $reservationBreakdown,
            'vacancy'              => $vacancy,
            'recentActivities'     => $recentActivities,
        ]);
    }

    public function export(Request $request)
    {
        [$from, $to] = $this->resolveRange($request);

        $landlordId = Auth::id();
        $properties = Property::where('landlord_id', $landlordId)->orderBy('title')->get();
        $units = PropertyUnit::whereIn('property_id', $properties->pluck('property_id'))->get();
        $revenueByPropertyId = $this->revenueByProperty($properties->pluck('property_id'), $from, $to);

        $filename = 'analytics-' . $from->format('Y-m-d') . '-to-' . $to->format('Y-m-d') . '.csv';

        // Streamed, matching the existing export pattern (OccupancyController).
        return response()->streamDownload(function () use ($properties, $units, $revenueByPropertyId) {
            $out = fopen('php://output', 'w');
            \App\Support\Csv::put($out, ['Property', 'Total Units', 'Occupied', 'Reserved', 'Available', 'Occupancy Rate (%)', 'Revenue (PHP)']);

            foreach ($properties as $property) {
                $propertyUnits = $units->where('property_id', $property->property_id);
                $total = $propertyUnits->count();
                $occupied = $propertyUnits->where('availability_status', 'Occupied')->count();

                \App\Support\Csv::put($out, [
                    $property->title,
                    $total,
                    $occupied,
                    $propertyUnits->where('availability_status', 'Reserved')->count(),
                    $propertyUnits->where('availability_status', 'Available')->count(),
                    $total > 0 ? round(($occupied / $total) * 100, 1) : 0,
                    number_format((float) ($revenueByPropertyId[$property->property_id] ?? 0), 2, '.', ''),
                ]);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * Money earned on a set of properties within a window.
     *
     * Dated by paid_at, not created_at: a checkout session created in one month
     * and paid in the next belongs to the month the money actually arrived.
     */
    private function revenueBetween($propertyIds, Carbon $from, Carbon $to): float
    {
        return (float) $this->revenueByProperty($propertyIds, $from, $to)->sum();
    }

    /**
     * Earned revenue per property in one grouped query — property_id => total.
     * Properties with no earnings in the window are simply absent.
     */
    private function revenueByProperty($propertyIds, Carbon $from, Carbon $to): Collection
    {
        $payments = $this->earnedPayments($propertyIds, $from, $to)
            ->groupBy('reservations.property_id')
            ->selectRaw('reservations.property_id as k, SUM(payments.amount) as total')
            ->pluck('total', 'k');

        $kept = $this->keptDepositCharges($propertyIds, $from, $to)
            ->groupBy('reservations.property_id')
            ->selectRaw('reservations.property_id as k, SUM(deposit_charges.amount) as total')
            ->pluck('total', 'k');

        return $this->addTotals($payments, $kept);
    }

    /** Earned revenue per calendar month for the trailing N months — 'Y-m' => total. */
    private function monthlyRevenue($propertyIds, int $months): Collection
    {
        $from = now()->startOfMonth()->subMonths($months - 1);
        $to = now()->endOfMonth();

        $payments = $this->earnedPayments($propertyIds, $from, $to)
            ->groupByRaw("DATE_FORMAT(payments.paid_at, '%Y-%m')")
            ->selectRaw("DATE_FORMAT(payments.paid_at, '%Y-%m') as k, SUM(payments.amount) as total")
            ->pluck('total', 'k');

        $kept = $this->keptDepositCharges($propertyIds, $from, $to)
            ->groupByRaw("DATE_FORMAT(deposit_charges.charged_at, '%Y-%m')")
            ->selectRaw("DATE_FORMAT(deposit_charges.charged_at, '%Y-%m') as k, SUM(deposit_charges.amount) as total")
            ->pluck('total', 'k');

        return $this->addTotals($payments, $kept);
    }

    /** Settled payments in the window, security deposits left out (see EARNED_STATUSES). */
    private function earnedPayments($propertyIds, Carbon $from, Carbon $to)
    {
        return Payment::query()
            ->join('reservations', 'reservations.reservation_id', '=', 'payments.reservation_id')
            ->whereIn('payments.status', self::EARNED_STATUSES)
            ->whereNotIn('payments.payment_type', self::NON_REVENUE_TYPES)
            ->whereBetween('payments.paid_at', [$from, $to])
            ->whereIn('reservations.property_id', $propertyIds);
    }

    /** Deposit the landlord kept (non-voided charges), dated by charged_at. */
    private function keptDepositCharges($propertyIds, Carbon $from, Carbon $to)
    {
        return DepositCharge::query()
            ->join('reservations', 'reservations.reservation_id', '=', 'deposit_charges.reservation_id')
            ->whereNull('deposit_charges.voided_at')
            ->whereBetween('deposit_charges.charged_at', [$from->toDateString(), $to->toDateString()])
            ->whereIn('reservations.property_id', $propertyIds);
    }

    /** Key-wise sum of two key => total maps, as floats. */
    private function addTotals(Collection $a, Collection $b): Collection
    {
        return $a->keys()->merge($b->keys())->unique()
            ->mapWithKeys(fn ($k) => [$k => (float) ($a[$k] ?? 0) + (float) ($b[$k] ?? 0)]);
    }

    /**
     * Security deposits the landlord is holding right now (point-in-time, not
     * range-filtered): collected minus what was kept, on every tenancy that
     * still holds its unit. A Completed tenancy is treated as settled — its
     * deposit was returned or charged at move-out.
     *
     * Read through RentLedger so a deposit folded into an older single online
     * Initial payment is counted the same way the tenancy page counts it.
     */
    private function depositsHeld($propertyIds): float
    {
        return (float) Reservation::whereIn('property_id', $propertyIds)
            ->whereIn('rental_status', Reservation::UNIT_HOLDING_STATUSES)
            ->with(['payments', 'depositCharges', 'lateFeeWaivers', 'unit'])
            ->get()
            ->sum(fn (Reservation $r) => RentLedger::for($r)->deposit()['remaining']);
    }

    private function activeReservationCount($propertyIds): int
    {
        return Reservation::whereIn('property_id', $propertyIds)
            ->whereNotIn('rental_status', Reservation::TERMINAL_STATUSES)
            ->count();
    }

    private function percentChange(float $previous, float $current): ?float
    {
        // Null, not 0 or 100 — "no prior data" is not the same as "no change",
        // and rendering a fake +100% on a landlord's first month is worse than
        // rendering nothing.
        if ($previous <= 0) {
            return null;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resolveRange(Request $request): array
    {
        return match ($request->query('range')) {
            'last_month' => [
                now()->subMonthNoOverflow()->startOfMonth(),
                now()->subMonthNoOverflow()->endOfMonth(),
            ],
            'last_3_months' => [now()->subMonths(2)->startOfMonth(), now()->endOfDay()],
            'this_year'     => [now()->startOfYear(), now()->endOfDay()],
            default         => [now()->startOfMonth(), now()->endOfDay()],
        };
    }
}
