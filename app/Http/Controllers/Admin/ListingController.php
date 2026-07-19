<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Property;
use Illuminate\Http\Request;

class ListingController extends Controller
{
    private const STATUSES = ['Pending', 'Approved', 'Rejected', 'All'];

    /**
     * Display the list of properties pending admin approval.
     */
    public function approval(Request $request)
    {
        $status = $request->query('status', 'Pending');

        if (! in_array($status, self::STATUSES, true)) {
            $status = 'Pending';
        }

        $query = Property::with('landlord')
            ->when($status !== 'All', fn ($q) => $q->where('verification_status', $status));

        $pendingListings = $status === 'Pending'
            ? $query->oldest()->paginate(15)->withQueryString()
            : $query->latest()->paginate(15)->withQueryString();

        $counts = [
            'Pending' => Property::where('verification_status', 'Pending')->count(),
            'Approved' => Property::where('verification_status', 'Approved')->count(),
            'Rejected' => Property::where('verification_status', 'Rejected')->count(),
        ];
        $counts['All'] = array_sum($counts);

        return view('admin.listings.approval', compact('pendingListings', 'status', 'counts'));
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