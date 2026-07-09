<?php

namespace App\Http\Controllers\Api\Landlord;

use App\Http\Controllers\Controller;
use App\Models\Property;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UnitController extends Controller
{
    /**
     * Units for one of the landlord's properties.
     */
    public function index(Request $request, Property $property): JsonResponse
    {
        if ($property->landlord_id !== $request->user()->user_id) {
            abort(403);
        }

        $units = $property->units()
            ->with('media')
            ->orderBy('unit_label')
            ->get();

        return response()->json([
            'data' => $units,
            'counts' => [
                'total'     => $units->count(),
                'available' => $units->where('availability_status', 'Available')->count(),
                'reserved'  => $units->where('availability_status', 'Reserved')->count(),
                'occupied'  => $units->where('availability_status', 'Occupied')->count(),
            ],
        ]);
    }
}
