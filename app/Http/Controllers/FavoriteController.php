<?php

namespace App\Http\Controllers;

use App\Models\Favorite;
use Illuminate\Http\Request;

class FavoriteController extends Controller
{
    public function index()
    {
        return view('favorites.index');
    }

    public function toggle(Request $request, int $propertyId)
    {
        $tenantId = auth()->user()->user_id;
        $isFavorited = Favorite::toggle($tenantId, $propertyId);

        return response()->json(['favorited' => $isFavorited]);
    }
}