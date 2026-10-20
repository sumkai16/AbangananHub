<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use Illuminate\Support\Facades\Auth;

class ProfileController extends Controller
{
    /**
     * Tenant views their own profile.
     */
    public function show()
    {
        $user = Auth::user();

        $reviews = $user->reviews()
            ->with(['property:property_id,title'])
            ->latest('created_at')
            ->get();

        $activeReservations = $user->reservations()
            ->whereNotIn('rental_status', Reservation::TERMINAL_STATUSES)
            ->with('property.media')
            ->latest('created_at')
            ->get();

        return view('tenant.profile.show', [
            'user' => $user,
            'favoritesCount' => $user->favorites()->count(),
            'reviews' => $reviews,
            'activeReservations' => $activeReservations,
            // How landlords have rated this tenant — collected via tenant_ratings
            // but never shown until now.
            'tenantRating' => $user->tenantRatingSummary(),
        ]);
    }
}
