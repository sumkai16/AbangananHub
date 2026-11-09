<?php

namespace App\Http\Controllers;

use App\Models\PlatformRating;
use Illuminate\Http\Request;

class PlatformRatingController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'rating'  => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000',
        ]);

        PlatformRating::updateOrCreate(
            ['user_id' => $request->user()->user_id],
            ['rating' => $validated['rating'], 'comment' => $validated['comment'] ?? null],
        );

        return redirect(route('home') . '#rate-us')->with('success', 'Thanks for rating AbangananHub!');
    }
}
