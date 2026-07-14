<?php

namespace App\Http\Controllers\Landlord;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class ReviewController extends Controller
{
    public function index(Request $request)
    {
        $landlordId = Auth::user()->user_id;

        $query = Review::where('landlord_id', $landlordId)
            ->with(['tenant', 'property']);

        if ($propertyId = $request->input('property')) {
            $query->where('property_id', $propertyId);
        }

        if ($rating = $request->input('rating')) {
            $query->where('rating', $rating);
        }

        if ($request->input('status') === 'replied') {
            $query->whereNotNull('landlord_reply');
        } elseif ($request->input('status') === 'unreplied') {
            $query->whereNull('landlord_reply');
        }

        $reviews = $query->latest()->paginate(12)->withQueryString();

        $properties = Auth::user()->properties()
            ->where('verification_status', 'Approved')
            ->orderBy('title')
            ->get(['property_id', 'title']);

        $stats = [
            'total'    => Review::where('landlord_id', $landlordId)->count(),
            'avg'      => round(Review::where('landlord_id', $landlordId)->where('is_hidden', false)->avg('rating') ?? 0, 1),
            'unreplied' => Review::where('landlord_id', $landlordId)->whereNull('landlord_reply')->count(),
        ];

        return view('landlord.reviews.index', compact('reviews', 'properties', 'stats'));
    }
    public function reply(Request $request, Review $review)
    {
        Gate::authorize('reply', $review);

        $validated = $request->validate([
            'landlord_reply' => 'required|string|max:1000',
        ]);

        $review->update([
            'landlord_reply'      => $validated['landlord_reply'],
            'landlord_replied_at' => now(),
        ]);

        Notification::create([
            'user_id'         => $review->tenant_id,
            'type'            => 'review',
            'notifiable_type' => Review::class,
            'notifiable_id'   => $review->review_id,
            'title'           => 'Landlord Reply',
            'message'         => Auth::user()->first_name . ' ' . Auth::user()->last_name . ' replied to your review on ' . $review->property->title . '.',
        ]);

        return back()->with('success', 'Reply submitted successfully.');
    }
}