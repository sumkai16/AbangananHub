<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

/**
 * Redeems the single-use ticket from Api\AuthController::webviewTicket,
 * logging the WebView into the session guard so `landlord.verification.*`
 * (a web-only wizard) renders as if the user had signed in normally.
 *
 * Deliberately outside the `auth`/`web` session-required middleware — a
 * fresh WebView has no session yet, that's the entire problem this solves.
 */
class WebviewLoginController extends Controller
{
    public function __invoke(Request $request, string $ticket)
    {
        $cacheKey = "webview_ticket:{$ticket}";
        $userId = Cache::get($cacheKey);

        // Deleted before use, not after: a request that dies mid-handler
        // (rare, but Auth::login() + session regen is not atomic) must not
        // leave a redeemable ticket behind for a retry to reuse.
        Cache::forget($cacheKey);

        if (! $userId) {
            abort(419, 'This link has expired. Return to the app and try again.');
        }

        $user = User::find($userId);

        if (! $user || $user->account_status === 'suspended') {
            abort(403);
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('landlord.verification.create');
    }
}
