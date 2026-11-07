<?php

namespace App\Http\Middleware;

use App\Models\Property;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * One property filter for the landlord pages that list or total things per property.
 *
 * The choice lives in the session (so it resets at the next login). A page's own Property dropdown sends
 * ?property=ID or ?property= (all); that is stored here too, so the sidebar always shows what the page shows.
 * When a request has no ?property, the stored choice is filled in, so every page's existing
 * `$request->input('property')` keeps working without knowing about the sidebar.
 */
class LandlordPropertyScope
{
    public const SESSION_KEY = 'landlord.property_scope';

    /**
     * Switched off for now: with this false the sidebar dropdown is hidden and no page is filtered by a remembered
     * property (a page's own Property dropdown keeps working as before). Set to true to bring it all back.
     */
    public const ENABLED = false;

    /** Pages that honour the filter (and their CSV exports). */
    private const PAGES = [
        'landlord.dashboard',
        'landlord.units.index',
        'landlord.reservations.index',
        'landlord.tenants.index', 'landlord.tenants.export',
        'landlord.payments.index',
        'landlord.reviews.index',
        'landlord.analytics.index', 'landlord.analytics.export',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (self::ENABLED && $request->isMethod('GET') && $request->routeIs(self::PAGES)) {
            if ($request->query->has('property')) {
                self::remember($request->user()->user_id, $request->integer('property'));
            } elseif ($id = self::current($request->user()->user_id)) {
                // GET input is the query bag, so input(), query() and request() all see it.
                $request->query->set('property', (string) $id);
            }
        }

        return $next($request);
    }

    /** Store a choice; 0, an unknown id or someone else's property clears it back to "all properties". */
    public static function remember(int $landlordId, int $propertyId): ?int
    {
        $id = $propertyId > 0 && self::owns($landlordId, $propertyId) ? $propertyId : null;
        $id === null ? session()->forget(self::SESSION_KEY) : session()->put(self::SESSION_KEY, $id);

        return $id;
    }

    /** The stored property, or null for all. Re-checked every time, so a deleted property cannot linger. */
    public static function current(int $landlordId): ?int
    {
        if (! self::ENABLED) {
            return null;
        }

        $id = (int) session(self::SESSION_KEY, 0);

        if ($id > 0 && self::owns($landlordId, $id)) {
            return $id;
        }

        session()->forget(self::SESSION_KEY);

        return null;
    }

    private static function owns(int $landlordId, int $propertyId): bool
    {
        return Property::where('landlord_id', $landlordId)->whereKey($propertyId)->exists();
    }
}
