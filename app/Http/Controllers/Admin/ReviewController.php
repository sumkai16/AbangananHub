<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ReviewController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('moderate', Review::class);

        $search     = $request->input('search');
        $rating     = $request->input('rating', 'all');
        $visibility = $request->input('visibility', 'all');
        $sort       = $request->input('sort', 'newest');

        // Search is shared by the list and the numbers above it; rating and visibility only narrow the list.
        $searched = Review::query()->when($search, function ($q) use ($search) {
            $q->where(function ($inner) use ($search) {
                $inner->whereHas('tenant', fn ($t) =>
                    $t->where('first_name', 'like', "%{$search}%")
                      ->orWhere('last_name',  'like', "%{$search}%")
                )->orWhereHas('property', fn ($p) =>
                    $p->where('title', 'like', "%{$search}%")
                )->orWhere('review_comment', 'like', "%{$search}%");
            });
        });

        $reviews = (clone $searched)->with(['tenant', 'property', 'landlord'])
            ->when($rating !== 'all', fn ($q) => $q->where('rating', $rating))
            ->when($visibility === 'visible', fn ($q) => $q->where('is_hidden', false))
            ->when($visibility === 'hidden', fn ($q) => $q->where('is_hidden', true));

        match ($sort) {
            'oldest' => $reviews->oldest(),
            'highest' => $reviews->orderByDesc('rating')->latest(),
            'lowest' => $reviews->orderBy('rating')->latest(),
            default => $reviews->latest(),
        };

        $reviews = $reviews->paginate(15)->withQueryString();

        $counts = [
            'all'     => (clone $searched)->count(),
            'visible' => (clone $searched)->where('is_hidden', false)->count(),
            'hidden'  => (clone $searched)->where('is_hidden', true)->count(),
            'low'     => (clone $searched)->where('rating', '<=', 2)->count(),
            'average' => (clone $searched)->avg('rating'),
        ];

        return view('admin.reviews.index', compact('reviews', 'counts', 'search', 'rating', 'visibility', 'sort'));
    }

    public function toggleHidden(Review $review)
    {
        Gate::authorize('moderate', Review::class);

        $review->update(['is_hidden' => !$review->is_hidden]);

        $action = $review->is_hidden ? 'hidden' : 'unhidden';

        AuditLog::record(
            'review.visibility_change',
            "Review #{$review->getKey()} {$action}.",
            $review,
            null,
            ['is_hidden' => $review->is_hidden ? 'yes' : 'no'],
        );

        return back()->with('success', "Review {$action} successfully.");
    }
}