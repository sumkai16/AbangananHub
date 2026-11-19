<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Security\SessionManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** An admin signs someone out of every device (a lost phone, a suspected break-in). Audited by SessionManager. */
class UserSessionController extends Controller
{
    public function __invoke(Request $request, User $user, SessionManager $sessions): RedirectResponse
    {
        if ($user->is($request->user())) {
            return back()->with('error', 'Use the Sign out button for your own account.');
        }

        $count = $sessions->revokeAll($user);

        return back()->with('success', "Signed out {$user->email} everywhere ({$count} ".($count === 1 ? 'session' : 'sessions').' ended).');
    }
}
