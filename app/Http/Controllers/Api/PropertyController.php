<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Favorite;
use App\Models\Property;
use App\Models\Review;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PropertyController extends Controller
{
    /**
     * Public paginated browse. Same query as the web PropertyController,
     * via the shared Property::browsable()/browseFilters() scopes.
     */
    public function index(Request $request): JsonResponse
    {
        $properties = Property::with(['media', 'landlord:user_id,first_name,last_name,profile_picture', 'amenities'])
            ->browsable()
            ->browseFilters([
                'location'  => $request->query('location'),
                'type'      => $request->query('type'),
                'price_max' => $request->query('price_max'),
                'verified'  => $request->boolean('verified'),
                'sort'      => $request->query('sort'),
            ])
            ->paginate(12)
            ->withQueryString();

        // Flag favorites for the requesting user, if a token was sent.
        $user = auth('sanctum')->user();
        $favoritedIds = $user
            ? Favorite::where('tenant_id', $user->user_id)->pluck('property_id')->all()
            : [];

        $properties->getCollection()->transform(function (Property $property) use ($favoritedIds) {
            $property->setAttribute('is_favorited', in_array($property->property_id, $favoritedIds));
            return $property;
        });

        return response()->json($properties);
    }

    /**
     * Public property detail with units, media, amenities, reviews, landlord.
     */
    public function show(Property $property): JsonResponse
    {
        if ($property->verification_status !== 'Approved') {
            abort(404);
        }

        $property->load([
            'media',
            'amenities',
            'landlord:user_id,first_name,last_name,profile_picture,created_at',
            'landlord.rentalBusiness',
            'units' => fn ($q) => $q->where('verification_status', 'Approved'),
            'units.media',
        ]);

        $reviews = $property->reviews()
            ->with('tenant:user_id,first_name,last_name,profile_picture')
            ->where('is_hidden', false)
            ->latest()
            ->get();

        $avgRating = $reviews->avg('rating');

        $user = auth('sanctum')->user();

        return response()->json([
            'data' => array_merge($property->toArray(), [
                'reviews'      => $reviews,
                'avg_rating'   => $avgRating ? round($avgRating, 1) : null,
                'review_count' => $reviews->count(),
                'can_review'   => $user ? Review::canReview($user->user_id, $property->property_id) : false,
                'is_favorited' => $user ? Favorite::isFavoritedBy($user->user_id, $property->property_id) : false,
            ]),
        ]);
    }
}
