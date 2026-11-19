<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RejectListingRequest;
use App\Models\AuditLog;
use App\Models\LandlordVerification;
use App\Models\Notification;
use App\Models\Property;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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
        $status = $request->query('status', 'All');

        if (! in_array($status, self::STATUSES, true)) {
            $status = 'All';
        }

        $query = Property::with(['landlord', 'media', 'units', 'documents'])
            ->withCount(['media as photos_count' => fn ($q) => $q->where('media_type', 'Image'), 'documents', 'documents as verified_documents_count' => fn ($q) => $q->where('status', 'Verified')])
            ->submitted()
            ->when($status === 'Suspended', fn ($q) => $q->where('publication_status', 'Suspended'))
            ->when(! in_array($status, ['All', 'Suspended'], true), fn ($q) => $q->where('verification_status', $status));

        // Waiting listings oldest first. On All they sit above everything else, then newest first.
        $pendingListings = match ($status) {
            'Pending' => $query->oldest(),
            'All' => $query->orderByRaw("properties.verification_status = 'Pending' desc")
                ->orderByRaw("case when properties.verification_status = 'Pending' then properties.created_at end asc")
                ->latest('properties.created_at'),
            default => $query->latest(),
        };
        $pendingListings = $pendingListings->paginate(10)->withQueryString();

        // One grouped query for the verification tabs instead of one count each.
        $byStatus = Property::submitted()
            ->selectRaw('verification_status, COUNT(*) as total')
            ->groupBy('verification_status')
            ->pluck('total', 'verification_status');

        $counts = [
            'Pending' => (int) ($byStatus['Pending'] ?? 0),
            'Approved' => (int) ($byStatus['Approved'] ?? 0),
            'Rejected' => (int) ($byStatus['Rejected'] ?? 0),
            'Suspended' => Property::where('publication_status', 'Suspended')->count(),
            'All' => (int) $byStatus->sum(),
        ];

        $since = now()->subDays(30);
        $summary = [
            'oldest'   => ($o = Property::submitted()->where('verification_status', 'Pending')->min('created_at')) ? Carbon::parse($o) : null,
            'approved' => Property::submitted()->where('verification_status', 'Approved')->where('updated_at', '>=', $since)->count(),
            'rejected' => Property::submitted()->where('verification_status', 'Rejected')->where('updated_at', '>=', $since)->count(),
            // Waiting listings with no photo or no unit: not ready to be shown to tenants.
            'incomplete' => Property::submitted()->where('verification_status', 'Pending')
                ->where(fn ($q) => $q->whereDoesntHave('media', fn ($m) => $m->where('media_type', 'Image'))->orWhereDoesntHave('units'))->count(),
        ];

        // The desk shows one listing in full: the one in ?open=, else the first on the page.
        $selected = $pendingListings->getCollection()->firstWhere('property_id', (int) $request->query('open')) ?? $pendingListings->first();
        // What the desk needs beyond the list row: each unit's photos, and whether the landlord's application was approved.
        $selected?->loadMissing('units.media');
        $landlordVerified = $selected ? LandlordVerification::where('user_id', $selected->landlord_id)->where('verification_status', 'Approved')->exists() : false;

        return view('admin.listings.approval', compact('pendingListings', 'status', 'counts', 'summary', 'selected', 'landlordVerified'));
    }

    /**
     * Approve a property listing, and the units that were submitted with it.
     *
     * One submission is one decision: every unit still Pending goes Approved in the same transaction. A unit the
     * admin already rejected stays Rejected (reject it first to hold it back). Units are updated one by one, not with
     * a mass update, so PropertyUnitObserver still writes "Unit X approved" to the property's activity log.
     * plans/approve-property-with-units.md
     */
    public function approve($property_id)
    {
        [$property, $unitCount] = DB::transaction(function () use ($property_id) {
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

            $units = $property->units()->where('verification_status', 'Pending')->lockForUpdate()->get();
            foreach ($units as $unit) {
                $unit->update(['verification_status' => 'Approved', 'rejection_reason' => null]);

                AuditLog::record(
                    'unit.approve',
                    "Approved unit '{$unit->unit_label}' in {$property->title}.",
                    $unit,
                    null,
                    ['property_id' => $property->property_id, 'landlord_id' => $property->landlord_id, 'via' => 'listing.approve'],
                );
            }

            return [$property, $units->count()];
        });

        $withUnits = $unitCount > 0 ? ', with '.$unitCount.' '.Str::plural('unit', $unitCount).',' : '';

        Notification::notify(
            $property->landlord_id,
            'listing',
            'Listing approved',
            "Your listing '{$property->title}' was approved{$withUnits} and is now visible to tenants.",
            route('properties.show', $property->property_id),
        );

        return redirect()->back()->with('success', "The listing '{$property->title}' has been approved successfully"
            .($unitCount > 0 ? ", with {$unitCount} ".Str::plural('unit', $unitCount).'.' : '.'));
    }

    /**
     * Reject a property listing.
     */
    public function reject(RejectListingRequest $request, $property_id)
    {
        $reason = $request->validated('rejection_reason');

        $property = DB::transaction(function () use ($property_id, $reason) {
            $property = Property::where('property_id', $property_id)->lockForUpdate()->firstOrFail();
            abort_if($property->verification_status !== 'Pending', 409, 'This listing has already been reviewed.');
            $property->update(['verification_status' => 'Rejected', 'rejection_reason' => $reason]);

            AuditLog::record(
                'listing.reject',
                "Rejected listing '{$property->title}'.",
                $property,
                null,
                ['landlord_id' => $property->landlord_id, 'reason' => $reason],
            );

            return $property;
        });

        Notification::notify(
            $property->landlord_id,
            'listing',
            'Listing not approved',
            "Your listing '{$property->title}' was not approved: {$reason}",
            route('landlord.properties.index'),
        );

        return redirect()->back()->with('error', "The listing '{$property->title}' has been rejected.");
    }

    /**
     * Suspend a listing straight from the admin page (no report needed).
     * Same state as a report's "delist property" action: publication_status
     * becomes Suspended, verification_status is left alone.
     */
    public function suspend(Request $request, $property_id)
    {
        $reason = $request->validate(['reason' => 'required|string|min:5|max:500'])['reason'];

        $property = DB::transaction(function () use ($property_id, $reason) {
            $property = Property::where('property_id', $property_id)->lockForUpdate()->firstOrFail();
            abort_if($property->publication_status === 'Suspended', 409, 'This listing is already suspended.');
            $property->update(['publication_status' => 'Suspended']);

            AuditLog::record(
                'listing.suspend',
                "Suspended listing '{$property->title}'.",
                $property,
                null,
                ['landlord_id' => $property->landlord_id, 'reason' => $reason],
            );

            return $property;
        });

        Notification::notify(
            $property->landlord_id,
            'listing',
            'Listing suspended',
            "Your listing '{$property->title}' was suspended and is hidden from tenants: {$reason}",
            route('landlord.properties.index'),
        );

        return redirect()->back()->with('error', "The listing '{$property->title}' has been suspended.");
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
