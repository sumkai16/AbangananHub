<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Models\PropertyUnit;
use Illuminate\Http\Request;

class PropertyUnitController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status', 'Pending');

        $query = PropertyUnit::with(['property.landlord', 'media']);

        if (in_array($status, ['Pending', 'Approved', 'Rejected'], true)) {
            $query->where('verification_status', $status);
        }
        // 'All' falls through with no filter

        if (in_array($status, ['Approved', 'Rejected'], true)) {
            $units = $query->latest('updated_at')->paginate(15)->withQueryString();
        } else {
            $units = $query->oldest()->paginate(15)->withQueryString();
        }

        return view('admin.units.index', compact('units', 'status'));
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
        abort_if(!$unit->isPending(), 409, 'This unit has already been reviewed.');
        $unit->update([
            'verification_status' => 'Approved',
            'rejection_reason'    => null,
        ]);
        return redirect()
            ->route('admin.units.index')
            ->with('success', "Unit '{$unit->unit_label}' approved.");
    }

    public function reject(Request $request, Property $property, PropertyUnit $unit)
    {
        if ($unit->property_id !== $property->property_id) {
            abort(404);
        }
        abort_if(!$unit->isPending(), 409, 'This unit has already been reviewed.');
        $validated = $request->validate([
           'rejection_reason' => 'required|string|max:500',
        ]);
        $unit->update([
            'verification_status' => 'Rejected',
            'rejection_reason'    => $validated['rejection_reason'] ?? null,
        ]);
        return redirect()
            ->route('admin.units.index')
            ->with('success', "Unit '{$unit->unit_label}' rejected.");
    }
}