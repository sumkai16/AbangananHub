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
        $rows = Review::where('is_hidden', false)
            ->selectRaw('landlord_id, AVG(rating) as avg, COUNT(*) as c')
            ->groupBy('landlord_id')
            ->havingRaw('COUNT(*) >= ?', [self::MIN_RATINGS_FOR_RANK])
            ->orderBy('avg', $direction)
            ->orderBy('c', 'desc')
            ->limit(5)
            ->get();

        $users = User::whereIn('user_id', $rows->pluck('landlord_id'))->get()->keyBy('user_id');

        return $rows
            ->map(fn ($row) => [
                'user'  => $users->get($row->landlord_id),
                'avg'   => round((float) $row->avg, 1),
                'count' => (int) $row->c,
            ])
            ->filter(fn ($r) => $r['user'] !== null)
            ->values();
    }

    private function tenantBoard(string $direction)
    {
        $rows = TenantRating::selectRaw('tenant_id, AVG(rating) as avg, COUNT(*) as c')
            ->groupBy('tenant_id')
            ->havingRaw('COUNT(*) >= ?', [1])
            ->orderBy('avg', $direction)
            ->orderBy('c', 'desc')
            ->limit(5)
            ->get();

        $users = User::whereIn('user_id', $rows->pluck('tenant_id'))->get()->keyBy('user_id');

        return $rows
            ->map(fn ($row) => [
                'user'  => $users->get($row->tenant_id),
                'avg'   => round((float) $row->avg, 1),
                'count' => (int) $row->c,
            ])
            ->filter(fn ($r) => $r['user'] !== null)
            ->values();
    }

    private function propertyBoard(string $direction)
    {
        $rows = Review::where('is_hidden', false)
            ->selectRaw('property_id, AVG(rating) as avg, COUNT(*) as c')
            ->groupBy('property_id')
            ->havingRaw('COUNT(*) >= ?', [self::MIN_RATINGS_FOR_RANK])
            ->orderBy('avg', $direction)
            ->orderBy('c', 'desc')
            ->limit(5)
            ->get();

        $properties = Property::whereIn('property_id', $rows->pluck('property_id'))->get()->keyBy('property_id');

        return $rows
            ->map(fn ($row) => [
                'property' => $properties->get($row->property_id),
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
        $from = now()->startOfMonth()->subMonths(5);

        // One grouped query per table for all six months, instead of several queries per month.
        $monthly = fn ($query) => $query->where('created_at', '>=', $from)
            ->groupByRaw("DATE_FORMAT(created_at, '%Y-%m')")
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as k, COUNT(*) as c, AVG(rating) as a")
            ->get()
            ->keyBy('k');

        $reviews = $monthly(Review::where('is_hidden', false));
        $ratings = $monthly(TenantRating::query());

        return collect(range(5, 0))->map(function ($monthsAgo) use ($reviews, $ratings) {
            $start = now()->startOfMonth()->subMonths($monthsAgo);
            $key = $start->format('Y-m');

            $rc = (int) ($reviews[$key]->c ?? 0);
            $tc = (int) ($ratings[$key]->c ?? 0);
            $total = $rc + $tc;

            $avg = $total > 0
                ? round(((((float) ($reviews[$key]->a ?? 0)) * $rc) + (((float) ($ratings[$key]->a ?? 0)) * $tc)) / $total, 2)
                : null;

            return ['label' => $start->format('M'), 'value' => $avg];
        })->values()->all();
    }
}
