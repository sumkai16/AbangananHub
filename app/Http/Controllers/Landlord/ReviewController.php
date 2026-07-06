<?php

namespace App\Http\Controllers\Landlord;

use App\Http\Controllers\Controller;
use App\Models\Review;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    /**
     * Store or update the landlord's reply on a review.
     */
    public function reply(Request $request, Review $review)
    {
        $this->authorize('reply', $review);

        $validated = $request->validate([
            'landlord_reply' => 'required|string|max:1000',
        ]);

        $review->update([
            'landlord_reply'     => $validated['landlord_reply'],
            'landlord_replied_at' => now(),
        ]);

        return back()->with('success', 'Reply submitted successfully.');
    }
}