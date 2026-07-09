<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Favorite;
use App\Models\Property;
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
        $favorites = Favorite::where('tenant_id', $request->user()->user_id)
            ->with(['property.media', 'property.units'])
            ->latest('created_at')
            ->get()
            // Skip favorites whose property was deleted or de-approved,
            // matching the web favorites page behaviour.
            ->filter(fn (Favorite $f) => $f->property && $f->property->verification_status === 'Approved')
            ->values()
            ->map(function (Favorite $favorite) {
                $property = $favorite->property;

                return [
                    'favorite_id' => $favorite->favorite_id,
                    'created_at'  => $favorite->created_at,
                    'property'    => array_merge($property->toArray(), [
                        'min_rental_fee'      => $property->min_rental_fee,
                        'availability_status' => $property->availability_status,
                    ]),
                ];
            });

        return response()->json(['data' => $favorites]);
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
