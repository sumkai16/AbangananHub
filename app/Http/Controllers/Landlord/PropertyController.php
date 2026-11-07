<?php

namespace App\Http\Controllers\Landlord;

use App\Http\Controllers\Concerns\AuthorizesPropertyOwnership;
use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Models\PropertyActivity;
use App\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PropertyController extends Controller
{
    use AuthorizesPropertyOwnership;

    public function index(Request $request)
    {
        $landlordId = Auth::user()->user_id;

        $query = Property::where('landlord_id', $landlordId)
            ->withCount([
                'units',
                'units as available_units_count' => fn($q) => $q->where('availability_status', 'Available'),
                'units as reserved_units_count'  => fn($q) => $q->where('availability_status', 'Reserved'),
                'units as occupied_units_count'  => fn($q) => $q->where('availability_status', 'Occupied'),
            ])
            ->with(['media' => fn($q) => $q->where('media_type', 'Image')->orderBy('media_id')->limit(1)]);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('address', 'like', "%{$search}%");
            });
        }

        if ($status = $request->input('status')) {
            // A Draft's verification_status is 'Pending' by default (the
            // column default, not a real submission) — filtering the
            // Pending bucket must not pull unsubmitted drafts into it.
            $query->where('verification_status', $status)->submitted();
        }

        if ($type = $request->input('type')) {
            $query->where('property_type', $type);
        }

        $properties = $query->latest()->paginate(9)->withQueryString();

        // Account-wide totals for the summary cards — deliberately unfiltered
        // by the search/status/type toolbar above, since "how many units do
        // I have total" shouldn't change just because the list below is
        // narrowed to one property type.
        $totalUnitsCount = \App\Models\PropertyUnit::whereHas('property', fn($q) => $q->where('landlord_id', $landlordId))->count();
        $stats = [
            'properties' => Property::where('landlord_id', $landlordId)->count(),
            'units'      => $totalUnitsCount,
            'occupied'   => \App\Models\PropertyUnit::whereHas('property', fn($q) => $q->where('landlord_id', $landlordId))
                ->where('availability_status', 'Occupied')->count(),
            'available'  => \App\Models\PropertyUnit::whereHas('property', fn($q) => $q->where('landlord_id', $landlordId))
                ->where('availability_status', 'Available')->count(),
        ];
        $stats['occupancyRate'] = $totalUnitsCount > 0 ? round($stats['occupied'] / $totalUnitsCount * 100) : 0;

        return view('landlord.properties.index', compact('properties', 'stats'));
    }

    public function show(Request $request, Property $property)
    {
        $this->authorizeProperty($property);

        $property->load([
            'media',
            'amenities',
            'units' => fn($q) => $q->orderBy('unit_label'),
            'units.media',
            'units.amenities',
            // Only live tenancies: the unit modal names the current tenant, nobody from the past.
            'units.reservations' => fn ($q) => $q->whereNotIn('rental_status', Reservation::TERMINAL_STATUSES)
                ->with('tenant:user_id,first_name,last_name'),
            'reviews' => fn($q) => $q->with('tenant')->latest()->take(20),
            'documents',
        ]);

        // Each unit already belongs to this page's property: hand it over so the modal payload never lazy-loads it.
        $property->units->each->setRelation('property', $property);

        $unitStats = [
            'total'    => $property->units->count(),
            'available' => $property->units->where('availability_status', 'Available')->count(),
            'reserved'  => $property->units->where('availability_status', 'Reserved')->count(),
            'occupied'  => $property->units->where('availability_status', 'Occupied')->count(),
        ];

        // The unit details modal, one payload per unit, shared by the Units tab and the Activity Log lines.
        $unitPayloads = $property->units->mapWithKeys(
            fn ($unit) => [$unit->unit_id => $unit->detailPayload($property, 'property')]
        );

        // Activity Log: the chip is a whitelisted word, never a column or a role typed by the visitor.
        $requested = $request->query('activity');
        $activityFilter = is_string($requested) && isset(PropertyActivity::FILTERS[$requested]) ? $requested : 'all';
        $roles = PropertyActivity::FILTERS[$activityFilter][1];

        $history = PropertyActivity::where('property_id', $property->property_id);

        $activities = (clone $history)
            ->when($roles, fn ($q) => $q->whereIn('actor_role', $roles))
            ->orderByDesc('created_at')->orderByDesc('activity_id')
            ->paginate(20, ['*'], 'activity_page')
            ->withQueryString()
            ->fragment('activity_log');

        $latestActivities = (clone $history)->orderByDesc('created_at')->orderByDesc('activity_id')->limit(3)->get();
        $activityCounts = (clone $history)->selectRaw('actor_role, count(*) as total')->groupBy('actor_role')->pluck('total', 'actor_role');

        // Coming back to a filtered or paged log keeps the tab open.
        $initialTab = $request->has('activity') || $request->has('activity_page') ? 'activity_log' : 'overview';

        return view('landlord.properties.show', compact(
            'property', 'unitStats', 'unitPayloads', 'activities', 'latestActivities', 'activityCounts', 'activityFilter', 'initialTab'
        ));
    }
}
