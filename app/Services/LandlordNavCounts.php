<?php

namespace App\Services;

use App\Models\Property;
use App\Models\Reservation;
use App\Models\Review;
use App\Models\TenantConcern;
use App\Models\ViewingRequest;

/**
 * The numbers on the landlord sidebar, one rule per item. Each counts what is waiting on the landlord, not what is
 * merely in a state: a listing waiting on admin or a sent agreement waiting on a tenant is not counted.
 *
 * Messages (live, Alpine store) and Payments (LandlordRentDueCount, cached and cleared by observers) have their own
 * sources and are not repeated here. Worked out once per request and kept on the request, never in a static.
 */
class LandlordNavCounts
{
    /**
     * @return array{reservations: int, viewings: int, concerns: int, properties: int, reviews: int, rentals: int}
     */
    public static function for(int $landlordId): array
    {
        $attributes = request()->attributes;
        $key = "landlord_nav_counts.{$landlordId}";
        $counts = $attributes->get($key);

        if ($counts === null) {
            $counts = self::compute($landlordId);
            $attributes->set($key, $counts);
        }

        return $counts;
    }

    private static function compute(int $landlordId): array
    {
        $ownsProperty = fn ($q) => $q->where('landlord_id', $landlordId);

        $inquiries = Reservation::whereHas('property', $ownsProperty)->where('rental_status', 'Inquiry')->count();

        // The same rule as the Viewings tab: asked by the tenant, still ahead of us, nobody answered yet.
        $viewings = ViewingRequest::where('landlord_id', $landlordId)
            ->open()
            ->where('status', 'Pending')
            ->where('proposed_by', '!=', $landlordId)
            ->count();

        // Listings the landlord has to fix: suspended, or rejected by admin (a draft is not finished, so it is not "rejected").
        $properties = Property::where('landlord_id', $landlordId)
            ->where(fn ($q) => $q->where('publication_status', 'Suspended')
                ->orWhere(fn ($r) => $r->where('publication_status', '!=', 'Draft')->where('verification_status', 'Rejected')))
            ->count();

        // Tenancies inside the renewal window: the same "ending" count as the Rentals page.
        $rentals = Reservation::whereHas('property', $ownsProperty)
            ->where('rental_status', 'Occupied')
            ->whereNotNull('target_move_out_date')
            ->whereDate('target_move_out_date', '<=', now()->addDays((int) config('rentals.renewal_window_days', 60)))
            ->count();

        return [
            'reservations' => $inquiries + $viewings,
            'viewings' => $viewings,
            'concerns' => TenantConcern::unresolved()->whereHas('conversation', fn ($q) => $q->where('landlord_id', $landlordId))->count(),
            'properties' => $properties,
            'reviews' => Review::where('landlord_id', $landlordId)->whereNull('landlord_reply')->count(),
            'rentals' => $rentals,
        ];
    }
}
