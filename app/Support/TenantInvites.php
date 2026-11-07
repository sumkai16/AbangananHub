<?php

namespace App\Support;

use App\Mail\TenantInviteMail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;

/**
 * Inviting a tenant the landlord added by hand to set a password. Until they accept, the account is a walk-in
 * (is_walk_in, inactive, unknowable password): it cannot log in and nothing can be sent to it in the app.
 * Accepting (TenantInviteController) makes it an ordinary tenant account.
 */
class TenantInvites
{
    public const BROKER = 'invites';

    /** True when this tenant can still be invited: added by hand, has an email, has not accepted yet. */
    public static function canInvite(User $tenant): bool
    {
        return $tenant->is_walk_in && filled($tenant->email);
    }

    public static function send(User $tenant, User $landlord): bool
    {
        if (! self::canInvite($tenant)) {
            return false;
        }

        $token = Password::broker(self::BROKER)->createToken($tenant);

        // Queued, not sent inline: talking to the mail server takes 4–5 s and made Add tenant hang. The queue
        // worker sends it (`composer run dev` runs one locally; the VPS needs a Supervisor `queue:work`).
        Mail::to($tenant->email)->queue(new TenantInviteMail($tenant, $landlord, $token));

        return true;
    }
}
