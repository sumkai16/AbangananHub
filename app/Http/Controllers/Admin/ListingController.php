<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Property;
use Illuminate\Http\Request;

class ListingController extends Controller
{
    /**
     * Display the list of properties pending admin approval.
     */
    public function approval()
    {
        // Fetch properties where verification_status is 'Pending'
        $pendingListings = Property::with('landlord')
            ->where('verification_status', 'Pending')
            ->latest()
            ->get();

        return view('admin.listings.approval', compact('pendingListings'));
    }

    /**
     * Approve a property listing.
     */
    public function approve($property_id)
    {
        $property = Property::where('property_id', $property_id)->firstOrFail();
        $property->update(['verification_status' => 'Approved']);

        return redirect()->back()->with('success', "The listing '{$property->title}' has been approved successfully.");
    }

    /**
     * Reject a property listing.
     */
    public function reject($property_id)
    {
        $property = Property::where('property_id', $property_id)->firstOrFail();
        $property->update(['verification_status' => 'Rejected']);

        return redirect()->back()->with('error', "The listing '{$property->title}' has been rejected.");
    }
}