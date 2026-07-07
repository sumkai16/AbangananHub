<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
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

        $reviews = Review::with(['tenant', 'property', 'landlord'])
            ->when($search, function ($q) use ($search) {
                $q->where(function ($inner) use ($search) {
                    $inner->whereHas('tenant', fn($t) =>
                        $t->where('first_name', 'like', "%{$search}%")
                          ->orWhere('last_name',  'like', "%{$search}%")
                    )->orWhereHas('property', fn($p) =>
                        $p->where('title', 'like', "%{$search}%")
                    )->orWhere('review_comment', 'like', "%{$search}%");
                });
            })
            ->when($rating !== 'all', fn($q) => $q->where('rating', $rating))
            ->when($visibility === 'visible', fn($q) => $q->where('is_hidden', false))
            ->when($visibility === 'hidden', fn($q) => $q->where('is_hidden', true))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $counts = [
            'all'     => Review::count(),
            'visible' => Review::where('is_hidden', false)->count(),
            'hidden'  => Review::where('is_hidden', true)->count(),
            1 => Review::where('rating', 1)->count(),
            2 => Review::where('rating', 2)->count(),
            3 => Review::where('rating', 3)->count(),
            4 => Review::where('rating', 4)->count(),
            5 => Review::where('rating', 5)->count(),
        ];

        return view('admin.reviews.index', compact('reviews', 'counts', 'search', 'rating', 'visibility'));
    }

    public function toggleHidden(Review $review)
    {
        Gate::authorize('moderate', Review::class);

        $review->update(['is_hidden' => !$review->is_hidden]);

        $action = $review->is_hidden ? 'hidden' : 'unhidden';

        return back()->with('success', "Review {$action} successfully.");
    }
}