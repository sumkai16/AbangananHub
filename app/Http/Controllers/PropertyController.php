<?php

namespace App\Http\Controllers;

use App\Models\Property;
use Illuminate\Http\Request;

class PropertyController extends Controller
{
    public function index(Request $request)
    {
        $query = Property::with(['media', 'landlord'])
            ->where('verification_status', 'Approved')
            ->where('availability_status', 'Available');

        if ($request->filled('type')) {
            $query->where('property_type', $request->type);
        }

        if ($request->filled('location')) {
            $query->where('address', 'like', '%' . $request->location . '%');
        }

        if ($request->boolean('verified')) {
            $query->where('verification_status', 'Approved');
        }

        $properties = $query->latest()->paginate(12)->withQueryString();

        return view('properties.index', compact('properties'));
    }

    public function show(Property $property)
    {
        $property->load(['media', 'landlord', 'amenities', 'reviews']);
        return view('properties.show', compact('property'));
    }

    public function create()
    {
        return view('properties.create');
    }

    public function store(Request $request)
    {
        // TODO: implement
        return redirect()->route('properties.index');
    }

    public function edit(Property $property)
    {
        return view('properties.edit', compact('property'));
    }

    public function update(Request $request, Property $property)
    {
        // TODO: implement
        return redirect()->route('properties.show', $property);
    }

    public function destroy(Property $property)
    {
        $property->delete();
        return redirect()->route('properties.index')->with('success', 'Property deleted.');
    }
}
