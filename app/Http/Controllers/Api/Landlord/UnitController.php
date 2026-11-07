<?php

namespace App\Http\Controllers\Api\Landlord;

use App\Http\Controllers\Controller;
use App\Http\Resources\PropertyUnitResource;
use App\Models\Property;
use App\Support\ApiPage;
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

        $page = $property->units()
            ->with('media')
            ->orderBy('unit_label')
            ->orderBy('unit_id')
            ->paginate(ApiPage::perPage($request))->withQueryString();

        // Counted over every unit, not just this page.
        $byStatus = $property->units()->selectRaw('availability_status, COUNT(*) as aggregate')
            ->groupBy('availability_status')->pluck('aggregate', 'availability_status');

        return response()->json([
            'data' => PropertyUnitResource::collection($page->getCollection()),
            'meta' => ApiPage::meta($page),
            'counts' => [
                'total'     => (int) $byStatus->sum(),
                'available' => (int) ($byStatus['Available'] ?? 0),
                'reserved'  => (int) ($byStatus['Reserved'] ?? 0),
                'occupied'  => (int) ($byStatus['Occupied'] ?? 0),
            ],
        ]);
    }
}
