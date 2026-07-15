<?php

namespace App\Http\Controllers\Landlord;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TenantController extends Controller
{
    public function index(Request $request)
    {
        $landlordId = Auth::user()->user_id;

        $query = Reservation::where('rental_status', 'Occupied')
            ->whereHas('property', fn($q) => $q->where('landlord_id', $landlordId))
            ->with(['tenant', 'property.media', 'unit', 'conversation']);

        if ($search = $request->input('search')) {
            $query->whereHas('tenant', function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($propertyId = $request->input('property')) {
            $query->where('property_id', $propertyId);
        }

        $reservations = $query->latest('reservation_date')->paginate(12)->withQueryString();

        $properties = Auth::user()->properties()
            ->where('verification_status', 'Approved')
            ->orderBy('title')
            ->get(['property_id', 'title']);

        return view('landlord.tenants.index', compact('reservations', 'properties'));
    }
}