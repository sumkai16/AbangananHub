<?php

namespace App\Http\Controllers\Landlord;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Property;
use App\Models\PropertyUnit;
use App\Models\Reservation;
use App\Services\OccupancyRateCalculator;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class AnalyticsController extends Controller
{
    /**
     * Revenue counts money the tenant has actually parted with: 'Held' is paid
     * and sitting in escrow, 'Released' has reached the landlord. 'Pending' is
     * a checkout session that may never complete, so it is deliberately not
     * revenue.
     */
    private const EARNED_STATUSES = ['Held', 'Released'];

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

        $revenue = $this->revenueBetween($propertyIds, $from, $to);
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
            ['label' => 'Available',   'count' => $availableUnits,   'color' => '#2AA7A1'],
            ['label' => 'Maintenance', 'count' => $maintenanceUnits, 'color' => '#94A3B8'],
        ];

        // ── Revenue over the last 6 months (line) ────────────
        $revenueTrend = collect(range(5, 0))->map(function ($monthsAgo) use ($propertyIds) {
            $start = now()->startOfMonth()->subMonths($monthsAgo);
            $end = (clone $start)->endOfMonth();

            return [
                'label' => $start->format('M'),
                'value' => $this->revenueBetween($propertyIds, $start, $end),
            ];
        })->values();

        // ── Per-property revenue + occupancy ─────────────────
        $perProperty = $properties->map(function (Property $property) use ($units, $from, $to) {
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
                'revenue'     => $this->revenueBetween(collect([$property->property_id]), $from, $to),
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
            ['label' => 'In progress', 'count' => (int) $statusCounts->only(['Inquiry', 'Under Negotiation', 'Pending Rental Agreement', 'Rental Agreement Signed'])->sum(), 'color' => '#FBBF24'],
            ['label' => 'Occupied',    'count' => (int) ($statusCounts['Occupied'] ?? 0),  'color' => '#22C55E'],
            ['label' => 'Cancelled',   'count' => (int) ($statusCounts['Cancelled'] ?? 0), 'color' => '#94A3B8'],
            ['label' => 'Rejected',    'count' => (int) ($statusCounts['Rejected'] ?? 0),  'color' => '#EF4444'],
        ];

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
        ]);
    }

    public function export(Request $request)
    {
        [$from, $to] = $this->resolveRange($request);

        $landlordId = Auth::id();
        $properties = Property::where('landlord_id', $landlordId)->orderBy('title')->get();
        $units = PropertyUnit::whereIn('property_id', $properties->pluck('property_id'))->get();

        $filename = 'analytics-' . $from->format('Y-m-d') . '-to-' . $to->format('Y-m-d') . '.csv';

        // Streamed, matching the existing export pattern (OccupancyController).
        return response()->streamDownload(function () use ($properties, $units, $from, $to) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Property', 'Total Units', 'Occupied', 'Reserved', 'Available', 'Occupancy Rate (%)', 'Revenue (PHP)']);

            foreach ($properties as $property) {
                $propertyUnits = $units->where('property_id', $property->property_id);
                $total = $propertyUnits->count();
                $occupied = $propertyUnits->where('availability_status', 'Occupied')->count();

                fputcsv($out, [
                    $property->title,
                    $total,
                    $occupied,
                    $propertyUnits->where('availability_status', 'Reserved')->count(),
                    $propertyUnits->where('availability_status', 'Available')->count(),
                    $total > 0 ? round(($occupied / $total) * 100, 1) : 0,
                    number_format($this->revenueBetween(collect([$property->property_id]), $from, $to), 2, '.', ''),
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
        return (float) Payment::whereIn('status', self::EARNED_STATUSES)
            ->whereBetween('paid_at', [$from, $to])
            ->whereHas('reservation', fn ($q) => $q->whereIn('property_id', $propertyIds))
            ->sum('amount');
    }

    private function activeReservationCount($propertyIds): int
    {
        return Reservation::whereIn('property_id', $propertyIds)
            ->whereNotIn('rental_status', ['Cancelled', 'Rejected'])
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
