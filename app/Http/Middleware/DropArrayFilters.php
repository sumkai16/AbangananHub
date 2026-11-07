<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * List pages read these query keys as single values (`$request->input('search')`, `->query('status')`).
 * A hand-edited URL like `?search[]=x` hands them an array instead and the page 500s. Values are
 * always bound, so this was never SQL injection, but a visitor could still crash any list page.
 * An array in one of these keys is simply dropped, the same as if it had not been sent.
 *
 * Keys that really are arrays (`amenities`, `rules`) are not listed; BrowseFilters::sanitize() cleans those.
 */
class DropArrayFilters
{
    private const SCALAR_KEYS = [
        'search', 'status', 'role', 'type', 'property_type', 'sort', 'health', 'filter', 'box', 'concern',
        'verification_status', 'availability_status', 'visibility', 'rating', 'action', 'section', 'time',
        'range', 'view', 'month', 'tab', 'starter', 'offset', 'from', 'to', 'property', 'property_id',
        'min_fee', 'max_fee', 'page', 'per_page', 'location', 'price_min', 'price_max',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethodSafe()) {
            foreach (self::SCALAR_KEYS as $key) {
                if (is_array($request->query($key))) {
                    $request->query->remove($key);
                    $request->request->remove($key);
                }
            }
        }

        return $next($request);
    }
}
