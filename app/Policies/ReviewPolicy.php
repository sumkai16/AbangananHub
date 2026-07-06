<?php

namespace App\Policies;

use App\Models\Review;
use App\Models\User;

class ReviewPolicy
{
    /**
     * Tenants can submit a review if they have an Occupied reservation
     * on the property and haven't already reviewed it.
     */
    public function create(User $user): bool
    {
        return $user->roles()->where('role', 'Tenant')->exists();
    }

    /**
     * Landlord can reply to reviews on their own properties.
     */
    public function reply(User $user, Review $review): bool
    {
        return $user->user_id === $review->landlord_id;
    }

    /**
     * Admin can toggle visibility (soft-hide) on any review.
     */
    public function moderate(User $user): bool
    {
        return $user->roles()->where('role', 'Admin')->exists();
    }
}