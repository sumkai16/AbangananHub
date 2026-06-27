<?php

namespace App\Http\Controllers;

use App\Models\Favorite;
use App\Models\Property;
use Illuminate\Http\Request;

class FavoriteController extends Controller
{
    public function index()
    {
        $tenantId = auth()->user()->user_id;

        $favorites = Favorite::where('tenant_id', $tenantId)
            ->with(['property.media', 'property.landlord.verificationApplication', 'property.amenities'])
            ->latest('created_at')
            ->get();

        // Favorited IDs for heart state — same pattern as properties index
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