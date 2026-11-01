<?php

namespace App\Http\Controllers\Landlord;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\Reservation;
use App\Models\TenantRating;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

/**
 * Rating a tenant happens in the contract modal on the Contracts page (_details, "Rate tenant").
 * The old page URL stays because "Rate your tenant" notifications link to it; it now opens that modal.
 */
class TenantRatingController extends Controller
{
    public function create(Reservation $reservation): RedirectResponse
    {
        if ($refusal = $this->refusal($reservation)) {
            return $refusal;
        }

        return redirect()->route('landlord.tenants.index', ['open' => $reservation->reservation_id, 'rate' => 1]);
    }

    public function store(Request $request, Reservation $reservation): RedirectResponse
    {
        if ($refusal = $this->refusal($reservation)) {
            return $refusal;
        }

        // The form lives in a modal fetched after the page loads, so session field errors would be lost:
        // the first error goes through the flash modal and the rating form opens again.
        $validator = Validator::make($request->all(), [
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000',
        ], [
            'rating.required' => 'Pick a rating from 1 to 5 stars.',
        ]);

        if ($validator->fails()) {
            return redirect()->route('landlord.tenants.index', ['open' => $reservation->reservation_id, 'rate' => 1])
                ->with('error', $validator->errors()->first());
        }

        $validated = $validator->validated();

        TenantRating::create([
            'reservation_id' => $reservation->reservation_id,
            'landlord_id' => Auth::id(),
            'tenant_id' => $reservation->tenant_id,
            'rating' => $validated['rating'],
            'comment' => $validated['comment'] ?? null,
        ]);

        $reservation->loadMissing('tenant');
        Notification::notify(
            $reservation->tenant?->is_walk_in ? null : $reservation->tenant_id,
            'tenant_rating',
            'You received a rating',
            'Your landlord left a '.$validated['rating'].'-star rating on your tenancy.',
            route('tenancy.show', $reservation),
        );

        return redirect()->route('landlord.tenants.index', ['open' => $reservation->reservation_id])->with('success', 'Tenant rated successfully.');
    }

    /**
     * Only the property's landlord, only on an Occupied or Completed contract (rating stays open after
     * Reservation::endTenancy() flips it to Completed), and only once.
     */
    private function refusal(Reservation $reservation): ?RedirectResponse
    {
        $property = $reservation->property;

        if (! $property || $property->landlord_id !== Auth::id()) {
            abort(403);
        }

        $back = fn (string $message) => redirect()->route('landlord.tenants.index', ['open' => $reservation->reservation_id])->with('error', $message);

        if (! in_array($reservation->rental_status, ['Occupied', 'Completed'], true)) {
            return $back('You can only rate tenants for occupied or completed rentals.');
        }

        if ($reservation->tenantRating) {
            return $back('You have already rated this tenant for this reservation.');
        }

        return null;
    }
}
