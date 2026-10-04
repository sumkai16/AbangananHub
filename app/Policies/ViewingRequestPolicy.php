<?php

namespace App\Policies;

use App\Models\User;
use App\Models\ViewingRequest;

class ViewingRequestPolicy
{
    /**
     * Tenant or landlord of this viewing: may reschedule or cancel it.
     * An offline viewing has no tenant account, so only its landlord.
     */
    public function participate(User $user, ViewingRequest $viewing): bool
    {
        return $viewing->isParticipant($user->user_id);
    }

    /**
     * Only the party who did NOT propose the current time may confirm or
     * decline it — confirming your own proposal would mean nothing. Never
     * true for an offline viewing: it is Confirmed when logged.
     */
    public function respond(User $user, ViewingRequest $viewing): bool
    {
        return $viewing->awaitsResponseFrom($user->user_id);
    }

    /**
     * Editing an offline viewing's visitor details or time directly, with no
     * confirmation step: there is nobody on the other end to confirm it.
     */
    public function manageOffline(User $user, ViewingRequest $viewing): bool
    {
        return $viewing->isOffline() && $user->user_id === $viewing->landlord_id;
    }
}
