<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;

/**
 * Enforces User::nameIsLocked() on every profile-update entry point. Done server-side by
 * putting the current name back into the request, so a hand-built request or the mobile API
 * cannot change it, and the "required" rules still pass when the form sent nothing.
 */
class NameLock
{
    public static function apply(Request $request, User $user): void
    {
        if ($user->nameIsLocked()) {
            $request->merge(['first_name' => $user->first_name, 'last_name' => $user->last_name]);
        }
    }

    public const NOTE = 'Your name is locked while your landlord verification is under review or approved, so it keeps matching your ID. Contact support if it needs to change.';
}
