<?php

namespace App\Http\Controllers\Landlord;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class ReviewController extends Controller
{
    public function reply(Request $request, Review $review)
    {
        Gate::authorize('reply', $review);

        $validated = $request->validate([
            'landlord_reply' => 'required|string|max:1000',
        ]);

        $review->update([
            'landlord_reply'      => $validated['landlord_reply'],
            'landlord_replied_at' => now(),
        ]);

        Notification::create([
            'user_id'         => $review->tenant_id,
            'type'            => 'review',
            'notifiable_type' => Review::class,
            'notifiable_id'   => $review->review_id,
            'title'           => 'Landlord Reply',
            'message'         => Auth::user()->first_name . ' ' . Auth::user()->last_name . ' replied to your review on ' . $review->property->title . '.',
        ]);

        return back()->with('success', 'Reply submitted successfully.');
    }
}