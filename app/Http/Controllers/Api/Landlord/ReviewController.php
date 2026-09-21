<?php

namespace App\Http\Controllers\Api\Landlord;

use App\Http\Controllers\Controller;
use App\Http\Resources\ReviewResource;
use App\Models\Notification;
use App\Models\Review;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Mobile equivalent of Landlord\ReviewController.
 */
class ReviewController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $landlordId = auth()->id();

        $query = Review::where('landlord_id', $landlordId)->with(['tenant', 'property']);

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
        $reviews->getCollection()->transform(fn (Review $r) => (new ReviewResource($r))->resolve());

        $properties = auth()->user()->properties()
            ->where('verification_status', 'Approved')
            ->orderBy('title')
            ->get(['property_id', 'title']);

        $stats = [
            'total'     => Review::where('landlord_id', $landlordId)->count(),
            'avg'       => round(Review::where('landlord_id', $landlordId)->where('is_hidden', false)->avg('rating') ?? 0, 1),
            'unreplied' => Review::where('landlord_id', $landlordId)->whereNull('landlord_reply')->count(),
        ];

        return response()->json(array_merge($reviews->toArray(), [
            'properties' => $properties,
            'stats'      => $stats,
        ]));
    }

    public function reply(Request $request, Review $review): JsonResponse
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
            'message'         => auth()->user()->first_name.' '.auth()->user()->last_name.' replied to your review on '.$review->property->title.'.',
        ]);

        return response()->json(['data' => new ReviewResource($review->fresh())]);
    }
}
