<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Review;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');
        $rating = $request->input('rating', 'all');

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
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $counts = [
            'all' => Review::count(),
            1 => Review::where('rating', 1)->count(),
            2 => Review::where('rating', 2)->count(),
            3 => Review::where('rating', 3)->count(),
            4 => Review::where('rating', 4)->count(),
            5 => Review::where('rating', 5)->count(),
        ];

        return view('admin.reviews.index', compact('reviews', 'counts', 'search', 'rating'));
    }

    public function destroy(Review $review)
    {
        $review->delete();

        return back()->with('success', 'Review removed successfully.');
    }
}
