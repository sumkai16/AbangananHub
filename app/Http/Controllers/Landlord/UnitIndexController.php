<?php

namespace App\Http\Controllers\Landlord;

use App\Http\Controllers\Controller;
use App\Models\PropertyUnit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UnitIndexController extends Controller
{
    public function index(Request $request)
    {
        $landlordId = Auth::user()->user_id;

        $query = PropertyUnit::whereHas('property', fn($q) => $q->where('landlord_id', $landlordId))
            ->with([
                'property',
                'property.media' => fn ($q) => $q->where('media_type', 'Image')->orderBy('media_id')->limit(1),
                'media' => fn ($q) => $q->where('media_type', 'Image')->orderBy('media_id')->limit(1),
                'amenities',
                'reservations.tenant:user_id,first_name,last_name',
            ]);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('unit_label', 'like', "%{$search}%")
                  ->orWhereHas('property', fn($pq) => $pq->where('title', 'like', "%{$search}%"));
            });
        }

        if ($propertyId = $request->input('property')) {
            $query->where('property_id', $propertyId);
        }

        if ($status = $request->input('status')) {
            $query->where('availability_status', $status);
        }

        $units = $query->latest()->paginate(12)->withQueryString();

        $properties = Auth::user()->properties()
            ->where('verification_status', 'Approved')
            ->orderBy('title')
            ->get(['property_id', 'title']);

        $stats = [
            'total'     => PropertyUnit::whereHas('property', fn($q) => $q->where('landlord_id', $landlordId))->count(),
            'available' => PropertyUnit::whereHas('property', fn($q) => $q->where('landlord_id', $landlordId))->where('availability_status', 'Available')->count(),
            'occupied'  => PropertyUnit::whereHas('property', fn($q) => $q->where('landlord_id', $landlordId))->where('availability_status', 'Occupied')->count(),
            'reserved'  => PropertyUnit::whereHas('property', fn($q) => $q->where('landlord_id', $landlordId))->where('availability_status', 'Reserved')->count(),
        ];
        return view('landlord.units.all', compact('units', 'properties', 'stats'));
    }
}