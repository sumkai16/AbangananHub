<?php

namespace App\Http\Controllers\Api\Landlord;

use App\Http\Controllers\Controller;
use App\Models\PropertyUnit;
use App\Models\Review;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class ProfileController extends Controller
{
    /**
     * Public landlord profile, gated by the user's profile_visibility
     * setting — same rules as the web Landlord\ProfileController@show.
     * The route is public; a Sanctum token (if sent) identifies the viewer.
     */
    public function show(User $user): JsonResponse
    {
        if (! $user->hasRole('Landlord')) {
            abort(404);
        }

        $viewer = auth('sanctum')->user();
        $isOwner = $viewer && $viewer->user_id === $user->user_id;

        if (! $isOwner) {
            $visibility = $user->profile_visibility ?? 'private';

            if ($visibility === 'private') {
                abort(404);
            }

            if ($visibility === 'landlords_only' && (! $viewer || ! $viewer->hasRole('Landlord'))) {
                abort(404);
            }
        }

        $properties = $user->properties()
            ->where('verification_status', 'Approved')
            ->with('media')
            ->latest('created_at')
            ->get();

        $propertyIds = $properties->pluck('property_id');

        $units = PropertyUnit::whereIn('property_id', $propertyIds)->get();

        $reviews = Review::whereIn('property_id', $propertyIds)
            ->with(['tenant:user_id,first_name,last_name,profile_picture', 'property:property_id,title'])
            ->latest('created_at')
            ->take(10)
            ->get();

        $averageRating = Review::whereIn('property_id', $propertyIds)->avg('rating');

        return response()->json([
            'data' => [
                'user' => $user->only([
                    'user_id', 'first_name', 'last_name', 'profile_picture', 'bio', 'created_at',
                ]),
                'is_owner'        => $isOwner,
                'business'        => $user->rentalBusiness,
                'is_verified'     => (bool) $user->rentalBusiness,
                'properties'      => $properties,
                'total_units'     => $units->count(),
                'occupied_units'  => $units->where('availability_status', 'Occupied')->count(),
                'reviews'         => $reviews,
                'average_rating'  => $averageRating ? round($averageRating, 1) : null,
            ],
        ]);
    }
}
