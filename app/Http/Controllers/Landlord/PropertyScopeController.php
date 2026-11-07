<?php

namespace App\Http\Controllers\Landlord;

use App\Http\Controllers\Controller;
use App\Http\Middleware\LandlordPropertyScope;
use Illuminate\Http\Request;

/** The sidebar's Property dropdown: remember the choice, then come back to the page the landlord was on. */
class PropertyScopeController extends Controller
{
    public function update(Request $request)
    {
        LandlordPropertyScope::remember($request->user()->user_id, (int) $request->input('property'));

        return redirect()->to($this->backUrl($request));
    }

    /** The page the landlord was on, without ?property (the stored choice fills it in) and ?page (a new filter starts at page 1). */
    private function backUrl(Request $request): string
    {
        $previous = parse_url((string) url()->previous());

        // Only come back to our own pages; a foreign or missing Referer goes to the dashboard.
        if (($previous['host'] ?? null) !== $request->getHost() || empty($previous['path'])) {
            return route('landlord.dashboard');
        }

        parse_str($previous['query'] ?? '', $query);
        unset($query['property'], $query['page']);

        return $request->getSchemeAndHttpHost().$previous['path'].($query ? '?'.http_build_query($query) : '');
    }
}
