<?php

namespace App\Http\Middleware;

use App\Models\Reservation;
use App\Services\Holds\HoldRestrictions;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Alias `tenant.hold`. Goes only on routes that start something new for a tenant (a reservation, a viewing request,
 * a new chat). Never on paying rent, claims, receipts, the lease, notifications or logout, so a tenant on hold can
 * always settle the debt. See plans/tenant-payment-holds.md.
 */
class EnsureNotOnHold
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! HoldRestrictions::isRestricted($user) || $this->actsAsLandlord($request, $user->user_id)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => HoldRestrictions::deniedMessage(),
                'code'    => 'account_on_hold',
            ], 403);
        }

        // The hold page is built in a later step of the plan; the literal path is its URL.
        return redirect('/account/on-hold')->with('warning', HoldRestrictions::deniedMessage());
    }

    /**
     * Shared routes (a viewing request is posted by the tenant and by the landlord) are only restricted for the
     * tenant: a user who owns the reservation's property is acting as a landlord there.
     */
    private function actsAsLandlord(Request $request, int $userId): bool
    {
        $reservation = $request->route('reservation');

        return $reservation instanceof Reservation
            && (int) $reservation->tenant_id !== $userId;
    }
}
