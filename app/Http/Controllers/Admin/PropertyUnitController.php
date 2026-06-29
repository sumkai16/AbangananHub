<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Models\PropertyUnit;
use Illuminate\Http\Request;

class PropertyUnitController extends Controller
{
    public function index()
    {
        $units = PropertyUnit::with(['property.landlord', 'media'])
            ->where('verification_status', 'Pending')
            ->latest()
            ->paginate(15);

        return view('admin.units.index', compact('units'));
    }

    public function show(Property $property, PropertyUnit $unit)
    {
        if ($unit->property_id !== $property->property_id) {
            abort(404);
        }

        $unit->load(['property.landlord', 'media']);
        return view('admin.units.show', compact('property', 'unit'));
    }

    public function approve(Property $property, PropertyUnit $unit)
    {
        if ($unit->property_id !== $property->property_id) {
            abort(404);
        }

        $unit->update(['verification_status' => 'Approved']);

        return redirect()
            ->route('admin.units.index')
            ->with('success', "Unit '{$unit->unit_label}' approved.");
    }

    public function reject(Request $request, Property $property, PropertyUnit $unit)
    {
        if ($unit->property_id !== $property->property_id) {
            abort(404);
        }

        $request->validate([
            'rejection_reason' => 'nullable|string|max:500',
        ]);

        $unit->update(['verification_status' => 'Rejected']);

        return redirect()
            ->route('admin.units.index')
            ->with('success', "Unit '{$unit->unit_label}' rejected.");
    }
}