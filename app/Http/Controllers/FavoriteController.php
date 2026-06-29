<?php

namespace App\Http\Controllers;

use App\Models\Favorite;
use App\Models\Property;
use Illuminate\Http\Request;

class FavoriteController extends Controller
{
    public function index(Request $request)
    {
        $tenantId = auth()->user()->user_id;
        $search = $request->query('search');
        $type = $request->query('type');
        $availability = $request->query('availability');

        $favoritesQuery = Favorite::where('tenant_id', $tenantId)
            ->with(['property.media', 'property.landlord.verificationApplication', 'property.amenities'])
            ->latest('created_at');

        if ($search) {
            $favoritesQuery->whereHas('property', fn($q) => $q->where('title', 'like', "%{$search}%")
                ->orWhere('address', 'like', "%{$search}%"));
        }

        if ($type) {
            $favoritesQuery->whereHas('property', fn($q) => $q->where('property_type', $type));
        }

        if ($availability) {
            $favoritesQuery->whereHas('property', fn($q) => $q->where('availability_status', $availability));
        }

        $favorites = $favoritesQuery->get();
        $favoritedIds = $favorites->pluck('property_id')->toArray();

        return view('favorites.index', compact('favorites', 'favoritedIds'));
    }

    public function toggle(Request $request, int $propertyId)
    {
        $tenantId = auth()->user()->user_id;
        $isFavorited = Favorite::toggle($tenantId, $propertyId);

        return response()->json(['favorited' => $isFavorited]);
    }
}