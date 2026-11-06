<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Notification;
use App\Models\Property;
use App\Models\PropertyUnit;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PropertyUnitController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status', 'Pending');

        $query = PropertyUnit::with(['property.landlord', 'media']);

        // Pending means "needs its own decision": a Pending unit on a Pending property is decided with the property.
        if ($status === 'Pending') {
            $query->awaitingOwnReview();
        } elseif (in_array($status, ['Approved', 'Rejected'], true)) {
            $query->where('verification_status', $status);
        }
        // 'All' falls through with no filter

        if (in_array($status, ['Approved', 'Rejected'], true)) {
            $units = $query->latest('updated_at')->paginate(15)->withQueryString();
        } else {
            $units = $query->oldest()->paginate(15)->withQueryString();
        }

        // 'All' is the real total, not the sum of the three: units waiting with a Pending property are in none of
        // the Pending count but are still on the All tab.
        $counts = [
            'Pending' => PropertyUnit::awaitingOwnReview()->count(),
            'Approved' => PropertyUnit::where('verification_status', 'Approved')->count(),
            'Rejected' => PropertyUnit::where('verification_status', 'Rejected')->count(),
            'All' => PropertyUnit::count(),
        ];

        $since = now()->subDays(30);
        $summary = [
            'oldest'   => ($o = PropertyUnit::awaitingOwnReview()->min('property_units.created_at')) ? Carbon::parse($o) : null,
            'approved' => PropertyUnit::where('verification_status', 'Approved')->where('updated_at', '>=', $since)->count(),
            'rejected' => PropertyUnit::where('verification_status', 'Rejected')->where('updated_at', '>=', $since)->count(),
            'no_photo' => PropertyUnit::awaitingOwnReview()->whereDoesntHave('media')->count(),
        ];
        $recent = PropertyUnit::with('property')->whereIn('verification_status', ['Approved', 'Rejected'])->latest('updated_at')->limit(5)->get();

        return view('admin.units.index', compact('units', 'status', 'counts', 'summary', 'recent'));
    }

    public function show(Property $property, PropertyUnit $unit)
    {
        if ($unit->property_id !== $property->property_id) {
            abort(404);
        }
        $unit->load(['property.landlord', 'media', 'activeReservation.tenant', 'reservations.tenant']);

        $siblings = $property->units()
            ->where('unit_id', '!=', $unit->unit_id)
            ->orderBy('unit_label')
            ->limit(10)
            ->get();

        return view('admin.units.show', compact('property', 'unit', 'siblings'));
    }

    public function approve(Property $property, PropertyUnit $unit)
    {
        if ($unit->property_id !== $property->property_id) {
            abort(404);
        }

        DB::transaction(function () use ($unit, $property) {
            $locked = PropertyUnit::whereKey($unit->getKey())->lockForUpdate()->firstOrFail();
            abort_if(!$locked->isPending(), 409, 'This unit has already been reviewed.');
            $locked->update([
                'verification_status' => 'Approved',
                'rejection_reason'    => null,
            ]);

            AuditLog::record(
                'unit.approve',
                "Approved unit '{$locked->unit_label}' in {$property->title}.",
                $locked,
                null,
                ['property_id' => $property->property_id, 'landlord_id' => $property->landlord_id],
            );
        });

        Notification::notify(
            $property->landlord_id,
            'listing',
            'Unit approved',
            "Unit '{$unit->unit_label}' in {$property->title} was approved and is now bookable.",
            route('landlord.units.index'),
        );

        return redirect()
            ->route('admin.units.index')
            ->with('success', "Unit '{$unit->unit_label}' approved.");
    }

    public function reject(Request $request, Property $property, PropertyUnit $unit)
    {
        if ($unit->property_id !== $property->property_id) {
            abort(404);
        }
        $validated = $request->validate([
            'rejection_reason' => 'required|string|max:500',
        ]);

        DB::transaction(function () use ($unit, $validated, $property) {
            $locked = PropertyUnit::whereKey($unit->getKey())->lockForUpdate()->firstOrFail();
            abort_if(!$locked->isPending(), 409, 'This unit has already been reviewed.');
            $locked->update([
                'verification_status' => 'Rejected',
                'rejection_reason'    => $validated['rejection_reason'] ?? null,
            ]);

            AuditLog::record(
                'unit.reject',
                "Rejected unit '{$locked->unit_label}' in {$property->title}.",
                $locked,
                $validated['rejection_reason'] ?? null,
                ['property_id' => $property->property_id, 'landlord_id' => $property->landlord_id],
            );
        });

        Notification::notify(
            $property->landlord_id,
            'listing',
            'Unit not approved',
            "Unit '{$unit->unit_label}' in {$property->title} was not approved. Reason: " . $validated['rejection_reason'],
            route('landlord.units.index'),
        );

        return redirect()
            ->route('admin.units.index')
            ->with('success', "Unit '{$unit->unit_label}' rejected.");
    }
}
