<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReviewController extends Controller
{
    /**
     * Store a new review for a property.
     */
    public function store(Request $request)
    {
        $this->authorize('create', Review::class);

        $validated = $request->validate([
            'property_id' => 'required|exists:properties,property_id',
            'rating'      => 'required|integer|min:1|max:5',
            'review_comment' => 'nullable|string|max:1000',
        ]);

        $property = Property::findOrFail($validated['property_id']);
        $tenantId = Auth::id();

        if (!Review::canReview($tenantId, $property->property_id)) {
            return back()->with('error', 'You can only review properties where you have an active rental.');
        }

        Review::create([
            'tenant_id'      => $tenantId,
            'property_id'    => $property->property_id,
            'landlord_id'    => $property->landlord_id,
            'rating'         => $validated['rating'],
            'review_comment' => $validated['review_comment'],
        ]);

        return back()->with('success', 'Review submitted successfully.');
    }
}