<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Notification;
use App\Models\Property;
use App\Models\PropertyUnit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PropertyUnitController extends Controller
{
    /**
     * The old Unit Approvals page. Its job moved into the Units catalogue (a "needs approval" filter with the same
     * Approve and Reject buttons), so this only keeps old links and bookmarks working.
     */
    public function index(Request $request)
    {
        $params = match ($request->query('status')) {
            'Pending' => ['needs' => 'approval'],
            'Approved', 'Rejected' => ['verification_status' => $request->query('status')],
            default => [],
        };

        return redirect()->route('admin.catalogue.units.index', $params);
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
            ->route('admin.catalogue.units.index', ['needs' => 'approval'])
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
            ->route('admin.catalogue.units.index', ['needs' => 'approval'])
            ->with('success', "Unit '{$unit->unit_label}' rejected.");
    }
}
