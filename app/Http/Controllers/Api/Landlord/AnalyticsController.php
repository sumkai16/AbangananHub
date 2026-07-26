<?php

namespace App\Http\Controllers\Api\Landlord;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Property;
use App\Models\PropertyUnit;
use App\Models\Reservation;
use App\Services\OccupancyRateCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Mobile equivalent of Landlord\AnalyticsController::index — same chart data,
 * already plain arrays server-side (the web view just feeds them to Chart.js).
 * CSV export is a desktop-only concern, not ported.
 */
class AnalyticsController extends Controller
{
    private const EARNED_STATUSES = ['Paid', 'Held', 'Released'];

    public function index(Request $request): JsonResponse
    {
        [$from, $to] = $this->resolveRange($request);

        $landlordId = auth()->id();
        $properties = Property::where('landlord_id', $landlordId)->orderBy('title')->get();
        $propertyIds = $properties->pluck('property_id');

        $units = PropertyUnit::whereIn('property_id', $propertyIds)->get();

        $occupiedUnits = $units->where('availability_status', 'Occupied')->count();
        $reservedUnits = $units->where('availability_status', 'Reserved')->count();
        $availableUnits = $units->where('availability_status', 'Available')->count();
        $maintenanceUnits = $units->where('availability_status', 'Maintenance')->count();

        $revenue = $this->revenueBetween($propertyIds, $from, $to);
        $activeReservations = $this->activeReservationCount($propertyIds);

        $spanDays = $from->diffInDays($to) + 1;
        $prevTo = (clone $from)->subDay()->endOfDay();
        $prevFrom = (clone $prevTo)->subDays($spanDays - 1)->startOfDay();

        $stats = [
            'properties'        => $properties->count(),
            'units'             => $units->count(),
            'occupied'          => $occupiedUnits,
            'revenue'           => $revenue,
            'reservations'      => $activeReservations,
            'occupancyRate'     => OccupancyRateCalculator::forLandlord($landlordId),
            'revenueDelta'      => $this->percentChange($this->revenueBetween($propertyIds, $prevFrom, $prevTo), $revenue),
            'reservationsDelta' => $this->percentChange(
                Reservation::whereIn('property_id', $propertyIds)->whereBetween('created_at', [$prevFrom, $prevTo])->count(),
                Reservation::whereIn('property_id', $propertyIds)->whereBetween('created_at', [$from, $to])->count()
            ),
        ];

        $occupancyBreakdown = [
            ['label' => 'Occupied',    'count' => $occupiedUnits,    'color' => '#22C55E'],
            ['label' => 'Reserved',    'count' => $reservedUnits,    'color' => '#FBBF24'],
            ['label' => 'Available',   'count' => $availableUnits,   'color' => '#2AA7A1'],
            ['label' => 'Maintenance', 'count' => $maintenanceUnits, 'color' => '#94A3B8'],
        ];

        $revenueTrend = collect(range(5, 0))->map(function ($monthsAgo) use ($propertyIds) {
            $start = now()->startOfMonth()->subMonths($monthsAgo);
            $end = (clone $start)->endOfMonth();

            return ['label' => $start->format('M'), 'value' => $this->revenueBetween($propertyIds, $start, $end)];
        })->values();

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

        $revenueByProperty = $perProperty->where('revenue', '>', 0)->values();
        $topSlices = $revenueByProperty->take(4);
        $othersTotal = $revenueByProperty->skip(4)->sum('revenue');

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

        return response()->json([
            'data' => [
                'from'                  => $from->toDateString(),
                'to'                    => $to->toDateString(),
                'range_key'             => $request->query('range', 'this_month'),
                'stats'                 => $stats,
                'occupancy_breakdown'   => $occupancyBreakdown,
                'revenue_trend'         => $revenueTrend,
                'per_property'          => $perProperty,
                'top_slices'            => $topSlices,
                'others_total'          => $othersTotal,
                'reservation_breakdown' => $reservationBreakdown,
            ],
        ]);
    }

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
            ->whereNotIn('rental_status', Reservation::TERMINAL_STATUSES)
            ->count();
    }

    private function percentChange(float $previous, float $current): ?float
    {
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
