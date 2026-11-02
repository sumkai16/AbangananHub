<?php

namespace App\Support;

use App\Models\Conversation;
use App\Models\Property;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * What a user can pick when they file a report without a pre-selected target: the /reports page and the
 * landlord's "File a report" modal on My Complaints read the same lists, so they cannot drift.
 */
class ReportTargets
{
    /** Listings by other landlords (nobody reports their own). */
    public static function properties(int $userId): Collection
    {
        return Property::where('landlord_id', '!=', $userId)
            ->orderBy('title')
            ->get(['property_id', 'title', 'landlord_id']);
    }

    /**
     * Only people this user has shared a conversation with. The list used to be every account, with email
     * addresses, so any logged-in user could harvest them. Anyone else is reported from their profile's
     * Report button (?user_id=), which pre-selects them.
     */
    public static function users(int $userId): Collection
    {
        $partnerIds = Conversation::where('tenant_id', $userId)->pluck('landlord_id')
            ->merge(Conversation::where('landlord_id', $userId)->pluck('tenant_id'))
            ->unique()
            ->reject(fn ($id) => (int) $id === $userId);

        return User::whereIn('user_id', $partnerIds)
            ->orderBy('first_name')
            ->get(['user_id', 'first_name', 'last_name']);
    }
}
