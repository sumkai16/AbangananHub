<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Notification;
use App\Models\Property;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ListingController extends Controller
{
    private const STATUSES = ['Pending', 'Approved', 'Rejected', 'Suspended', 'All'];

    /**
     * Display the list of properties pending admin approval.
     *
     * 'Suspended' filters on publication_status instead of verification_status
     * — a suspended property keeps whatever verification_status it already
     * had (almost always Approved, since it was live before a report took it
     * down), so it would otherwise be invisible in this queue, findable only
     * by scrolling the Approved tab.
     */
    public function approval(Request $request)
    {
        $status = $request->query('status', 'Pending');

        if (! in_array($status, self::STATUSES, true)) {
            $status = 'Pending';
        }

        $query = Property::with(['landlord', 'media', 'units'])
            ->submitted()
            ->when($status === 'Suspended', fn ($q) => $q->where('publication_status', 'Suspended'))
            ->when(! in_array($status, ['All', 'Suspended'], true), fn ($q) => $q->where('verification_status', $status));

        $pendingListings = $status === 'Pending'
            ? $query->oldest()->paginate(15)->withQueryString()
            : $query->latest()->paginate(15)->withQueryString();

        $counts = [
            'Pending' => Property::submitted()->where('verification_status', 'Pending')->count(),
            'Approved' => Property::submitted()->where('verification_status', 'Approved')->count(),
            'Rejected' => Property::submitted()->where('verification_status', 'Rejected')->count(),
            'Suspended' => Property::where('publication_status', 'Suspended')->count(),
        ];
        $counts['All'] = Property::submitted()->count();

        return view('admin.listings.approval', compact('pendingListings', 'status', 'counts'));
    }

    /**
     * Approve a property listing.
     */
    public function approve($property_id)
    {
        $property = DB::transaction(function () use ($property_id) {
            $property = Property::where('property_id', $property_id)->lockForUpdate()->firstOrFail();
            abort_if($property->verification_status !== 'Pending', 409, 'This listing has already been reviewed.');
            $property->update(['verification_status' => 'Approved']);

            AuditLog::record(
                'listing.approve',
                "Approved listing '{$property->title}'.",
                $property,
                null,
                ['landlord_id' => $property->landlord_id],
            );

            return $property;
        });

        Notification::notify(
            $property->landlord_id,
            'listing',
            'Listing approved',
            "Your listing '{$property->title}' was approved and is now visible to tenants.",
            route('properties.show', $property->property_id),
        );

        return redirect()->back()->with('success', "The listing '{$property->title}' has been approved successfully.");
    }

    /**
     * Reject a property listing.
     */
    public function reject($property_id)
    {
        $property = DB::transaction(function () use ($property_id) {
            $property = Property::where('property_id', $property_id)->lockForUpdate()->firstOrFail();
            abort_if($property->verification_status !== 'Pending', 409, 'This listing has already been reviewed.');
            $property->update(['verification_status' => 'Rejected']);

            AuditLog::record(
                'listing.reject',
                "Rejected listing '{$property->title}'.",
                $property,
                null,
                ['landlord_id' => $property->landlord_id],
            );

            return $property;
        });

        Notification::notify(
            $property->landlord_id,
            'listing',
            'Listing not approved',
            "Your listing '{$property->title}' was not approved. Review the details and resubmit.",
            route('landlord.properties.index'),
        );

        return redirect()->back()->with('error', "The listing '{$property->title}' has been rejected.");
    }

    /**
     * Lift a moderation suspension (set via a report's "delist property"
     * action). The only path back to Published from Suspended — a landlord
     * cannot self-clear this via publish/unpublish.
     */
    public function unsuspend($property_id)
    {
        $property = DB::transaction(function () use ($property_id) {
            $property = Property::where('property_id', $property_id)->lockForUpdate()->firstOrFail();
            abort_if($property->publication_status !== 'Suspended', 409, 'This listing is not suspended.');
            $property->update(['publication_status' => 'Published']);

            AuditLog::record(
                'listing.unsuspend',
                "Unsuspended listing '{$property->title}'.",
                $property,
                null,
                ['landlord_id' => $property->landlord_id],
            );

            return $property;
        });

        Notification::notify(
            $property->landlord_id,
            'listing',
            'Listing reinstated',
            "Your listing '{$property->title}' is visible to tenants again.",
            route('properties.show', $property->property_id),
        );

        return redirect()->back()->with('success', "The listing '{$property->title}' has been reinstated.");
    }
}