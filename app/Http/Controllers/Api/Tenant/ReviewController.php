<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\Property;
use App\Models\Review;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ReviewController extends Controller
{
    /**
     * Submit a review. Same eligibility rules and landlord
     * notification as the web Tenant\ReviewController@store.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'property_id'    => 'required|exists:properties,property_id',
            'rating'         => 'required|integer|min:1|max:5',
            'review_comment' => 'nullable|string|max:1000',
        ]);

        $user = $request->user();
        $property = Property::findOrFail($validated['property_id']);

        if (! Review::canReview($user->user_id, $property->property_id)) {
            throw ValidationException::withMessages([
                'property_id' => ['You can only review properties where you have an active rental.'],
            ]);
        }

        $review = Review::create([
            'tenant_id'      => $user->user_id,
            'property_id'    => $property->property_id,
            'landlord_id'    => $property->landlord_id,
            'rating'         => $validated['rating'],
            'review_comment' => $validated['review_comment'] ?? null,
        ]);

        Notification::create([
            'user_id'         => $property->landlord_id,
            'type'            => 'review',
            'notifiable_type' => Review::class,
            'notifiable_id'   => $review->review_id,
            'title'           => 'New Review',
            'message'         => $user->first_name . ' ' . $user->last_name . ' left a ' . $validated['rating'] . '-star review on ' . $property->title . '.',
        ]);

        return response()->json(['data' => $review], 201);
    }
}
