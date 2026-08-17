<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
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
            ->with(['property.media', 'property.landlord.verificationApplication', 'property.amenities', 'property.units'])
            ->latest('created_at');

        if ($search) {
            $favoritesQuery->whereHas('property', fn($q) => $q->where('title', 'like', "%{$search}%")
                ->orWhere('address', 'like', "%{$search}%"));
        }

        if ($type) {
            $favoritesQuery->whereHas('property', fn($q) => $q->where('property_type', $type));
        }

        // availability_status lives on property_units, not properties — "available"
        // means the property has at least one available unit, same definition
        // PropertyController::index uses for the public browse filter.
        if ($availability === 'Available') {
            $favoritesQuery->whereHas('property.units', fn($q) => $q->where('availability_status', 'Available'));
        } elseif ($availability === 'Unavailable') {
            $favoritesQuery->whereDoesntHave('property.units', fn($q) => $q->where('availability_status', 'Available'));
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