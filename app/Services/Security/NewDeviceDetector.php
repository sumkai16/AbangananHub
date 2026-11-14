<?php

namespace App\Services\Security;

use App\Models\AuthEvent;
use App\Models\User;

/**
 * Decides whether a sign-in just recorded is from a browser the user has not signed in from lately. The just-recorded
 * row is excluded by id, so it never counts as its own "earlier" login.
 */
class NewDeviceDetector
{
    public function isNew(User $user, ?string $deviceId, ?int $currentEventId = null): bool
    {
        $earlier = AuthEvent::where('user_id', $user->user_id)
            ->where('event', 'login')
            ->when($currentEventId, fn ($q) => $q->where('event_id', '<', $currentEventId));

        // First-ever sign-in: nothing to compare with, and an alert would only be noise.
        if (! (clone $earlier)->exists()) {
            return false;
        }

        if ($deviceId === null || $deviceId === '') {
            return true;
        }

        return ! (clone $earlier)
            ->where('device_id', $deviceId)
            ->where('created_at', '>=', now()->subDays(90))
            ->exists();
    }
}
