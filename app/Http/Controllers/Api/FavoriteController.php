<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PropertyResource;
use App\Models\Favorite;
use App\Models\Property;
use App\Support\ApiPage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class FavoriteController extends Controller
{
    /**
     * The requesting tenant's favorited properties.
     */
    public function index(Request $request): JsonResponse
    {
        $page = Favorite::where('tenant_id', $request->user()->user_id)
            // Skip favorites whose property was deleted or de-approved,
            // matching the web favorites page behaviour.
            ->whereHas('property', fn ($q) => $q->where('verification_status', 'Approved'))
            ->with(['property.media', 'property.units'])
            ->latest('created_at')
            ->orderByDesc('favorite_id')
            ->paginate(ApiPage::perPage($request))->withQueryString();

        $favorites = $page->getCollection()
            ->map(function (Favorite $favorite) {
                $property = $favorite->property;

                return [
                    'favorite_id' => $favorite->favorite_id,
                    'created_at'  => $favorite->created_at,
                    'property'    => array_merge((new PropertyResource($property))->resolve(), [
                        'min_rental_fee'      => $property->min_rental_fee,
                        'availability_status' => $property->availability_status,
                    ]),
                ];
            });

        return response()->json(['data' => $favorites->values(), 'meta' => ApiPage::meta($page)]);
    }

    /**
     * Toggle a favorite on/off for the requesting tenant.
     */
    public function toggle(Request $request, int $propertyId): JsonResponse
    {
        if (! Property::where('property_id', $propertyId)->exists()) {
            throw ValidationException::withMessages([
                'property' => ['Property not found.'],
            ]);
        }

        $favorited = Favorite::toggle($request->user()->user_id, $propertyId);

        return response()->json(['data' => ['favorited' => $favorited]]);
    }
}
