<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Services\Security\SessionManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** The signed-in user ends their own other sessions. Always asks for the current password first. */
class SessionController extends Controller
{
    private const BAG = 'sessions';

    public function __construct(private SessionManager $sessions)
    {
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        $request->validateWithBag(self::BAG, ['password' => ['bail', 'required', 'string', 'current_password']]);

        if (hash_equals($id, $request->session()->getId())) {
            return back()->withErrors(['session' => 'This is the device you are using now. Use Sign out to leave it.'], self::BAG);
        }

        abort_unless($this->sessions->revokeOne($request->user(), $id), 404);

        return back()->with('success', 'That device is signed out.');
    }

    public function destroyOthers(Request $request): RedirectResponse
    {
        $request->validateWithBag(self::BAG, ['password' => ['bail', 'required', 'string', 'current_password']]);

        $count = $this->sessions->revokeOthers($request->user(), $request->session()->getId());

        return back()->with('success', $count === 1 ? 'Signed out 1 other device.' : "Signed out {$count} other devices.");
    }
}
