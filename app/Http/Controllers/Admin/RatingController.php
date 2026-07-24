<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Models\Review;
use App\Models\TenantRating;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Platform-wide ratings overview for the admin.
 *
 * Pure aggregation over the two rating tables — no new capture. Two directions,
 * kept separate because they measure different things from different raters:
 *   - reviews:       tenant -> property (rolled up to landlord by landlord_id)
 *   - tenant_ratings: landlord -> tenant
 *
 * Hidden reviews (is_hidden) are excluded everywhere, matching every other
 * rating display in the app. Unit-grain ratings are deferred — reviews carry no
 * unit_id.
 */
class RatingController extends Controller
{
    // A leaderboard needs a floor of ratings before an average means anything,
    // or a single 5-star review tops the list.
    private const MIN_RATINGS_FOR_RANK = 2;

    public function index()
    {
        $reviewAvg = (float) Review::where('is_hidden', false)->avg('rating');
        $reviewCount = Review::where('is_hidden', false)->count();
        $tenantAvg = (float) TenantRating::avg('rating');
        $tenantCount = TenantRating::count();

        $totalCount = $reviewCount + $tenantCount;
        $platformAvg = $totalCount > 0
            ? round((($reviewAvg * $reviewCount) + ($tenantAvg * $tenantCount)) / $totalCount, 2)
            : null;

        return view('admin.ratings.index', [
            'platformAvg'   => $platformAvg,
            'totalCount'    => $totalCount,
            'relationships' => [
                [
                    'key'   => 'tenant_property',
                    'label' => 'Tenant → Property',
                    'sub'   => 'How tenants rate the places they stayed',
                    'avg'   => $reviewCount > 0 ? round($reviewAvg, 2) : null,
                    'count' => $reviewCount,
                    'dist'  => $this->distribution(Review::where('is_hidden', false)),
                ],
                [
                    'key'   => 'tenant_landlord',
                    'label' => 'Tenant → Landlord',
                    'sub'   => 'The same reviews, rolled up per landlord',
                    'avg'   => $reviewCount > 0 ? round($reviewAvg, 2) : null,
                    'count' => $reviewCount,
                    'dist'  => $this->distribution(Review::where('is_hidden', false)),
                    'note'  => 'Shares its source with Tenant → Property.',
                ],
                [
                    'key'   => 'landlord_tenant',
                    'label' => 'Landlord → Tenant',
                    'sub'   => 'How landlords rate their tenants',
                    'avg'   => $tenantCount > 0 ? round($tenantAvg, 2) : null,
                    'count' => $tenantCount,
                    'dist'  => $this->distribution(TenantRating::query()),
                ],
            ],
            'topLandlords'    => $this->landlordBoard('desc'),
            'lowLandlords'    => $this->landlordBoard('asc'),
            'topTenants'      => $this->tenantBoard('desc'),
            'topProperties'   => $this->propertyBoard('desc'),
            'lowProperties'   => $this->propertyBoard('asc'),
            'trend'           => $this->trend(),
        ]);
    }

    /**
     * 5★ → 1★ counts for a rating query, as a fixed-order array so the bars
     * always render all five rows even when a star has zero.
     */
    private function distribution($query): array
    {
        $counts = (clone $query)
            ->selectRaw('rating, COUNT(*) as c')
            ->groupBy('rating')
            ->pluck('c', 'rating');

        $total = $counts->sum();

        return collect(range(5, 1))->map(fn ($star) => [
            'star'  => $star,
            'count' => (int) ($counts[$star] ?? 0),
            'pct'   => $total > 0 ? round((($counts[$star] ?? 0) / $total) * 100) : 0,
        ])->all();
    }

    /**
     * Landlords ranked by the average review across their properties, with a
     * minimum-ratings floor so a lone 5-star can't top the board.
     */
    private function landlordBoard(string $direction)
    {
        return Review::where('is_hidden', false)
            ->selectRaw('landlord_id, AVG(rating) as avg, COUNT(*) as c')
            ->groupBy('landlord_id')
            ->havingRaw('COUNT(*) >= ?', [self::MIN_RATINGS_FOR_RANK])
            ->orderBy('avg', $direction)
            ->orderBy('c', 'desc')
            ->limit(5)
            ->get()
            ->map(fn ($row) => [
                'user'  => User::find($row->landlord_id),
                'avg'   => round((float) $row->avg, 1),
                'count' => (int) $row->c,
            ])
            ->filter(fn ($r) => $r['user'] !== null)
            ->values();
    }

    private function tenantBoard(string $direction)
    {
        return TenantRating::selectRaw('tenant_id, AVG(rating) as avg, COUNT(*) as c')
            ->groupBy('tenant_id')
            ->havingRaw('COUNT(*) >= ?', [1])
            ->orderBy('avg', $direction)
            ->orderBy('c', 'desc')
            ->limit(5)
            ->get()
            ->map(fn ($row) => [
                'user'  => User::find($row->tenant_id),
                'avg'   => round((float) $row->avg, 1),
                'count' => (int) $row->c,
            ])
            ->filter(fn ($r) => $r['user'] !== null)
            ->values();
    }

    private function propertyBoard(string $direction)
    {
        return Review::where('is_hidden', false)
            ->selectRaw('property_id, AVG(rating) as avg, COUNT(*) as c')
            ->groupBy('property_id')
            ->havingRaw('COUNT(*) >= ?', [self::MIN_RATINGS_FOR_RANK])
            ->orderBy('avg', $direction)
            ->orderBy('c', 'desc')
            ->limit(5)
            ->get()
            ->map(fn ($row) => [
                'property' => Property::find($row->property_id),
                'avg'      => round((float) $row->avg, 1),
                'count'    => (int) $row->c,
            ])
            ->filter(fn ($r) => $r['property'] !== null)
            ->values();
    }

    /**
     * Combined monthly average across both rating tables over the last six
     * months, for the trend line. Same shape as Landlord\AnalyticsController's
     * revenue trend.
     */
    private function trend(): array
    {
        return collect(range(5, 0))->map(function ($monthsAgo) {
            $start = now()->startOfMonth()->subMonths($monthsAgo);
            $end = (clone $start)->endOfMonth();

            $r = Review::where('is_hidden', false)->whereBetween('created_at', [$start, $end]);
            $t = TenantRating::whereBetween('created_at', [$start, $end]);

            $rc = (clone $r)->count();
            $tc = (clone $t)->count();
            $total = $rc + $tc;

            $avg = $total > 0
                ? round((((float) (clone $r)->avg('rating') * $rc) + ((float) (clone $t)->avg('rating') * $tc)) / $total, 2)
                : null;

            return ['label' => $start->format('M'), 'value' => $avg];
        })->values()->all();
    }
}
