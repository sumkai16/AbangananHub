<?php

namespace App\Observers;

use App\Models\PropertyActivity;
use App\Models\Review;
use App\Models\User;

/** A tenant's review of the property. Replies, hiding and edits are not history. */
class ReviewObserver
{
    public function created(Review $review): void
    {
        PropertyActivity::safely(function () use ($review) {
            if (! $review->property_id) {
                return;
            }

            $tenant = User::query()->whereKey($review->tenant_id)->first(['user_id', 'first_name', 'last_name']);

            PropertyActivity::record(
                $review->property_id,
                'review.posted',
                PropertyActivity::nameOf($review->tenant_id).' left a '.(int) $review->rating.'-star review',
                [
                    'actor'    => $tenant,
                    'role'     => 'tenant',
                    'metadata' => ['review_id' => $review->review_id, 'rating' => (int) $review->rating],
                    'at'       => $review->created_at ?? now(),
                ],
            );
        });
    }
}
