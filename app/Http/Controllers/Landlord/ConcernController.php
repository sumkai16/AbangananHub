<?php

namespace App\Http\Controllers\Landlord;

use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Models\TenantConcern;
use Illuminate\Http\Request;

/**
 * The landlord's Concerns page: every concern their tenants reported from the tenancy chat, in one list they can work
 * through. Reading and filtering only; moving a concern to In Progress or Fixed uses TenantConcernController::updateStatus
 * (the same route and policy as the chat card).
 */
class ConcernController extends Controller
{
    public const FILTERS = ['active' => 'Needs attention', 'Open' => 'Open', 'In Progress' => 'In progress', 'Fixed' => 'Fixed', 'all' => 'All'];

    public function index(Request $request)
    {
        $landlordId = $request->user()->user_id;

        $properties = Property::where('landlord_id', $landlordId)->orderBy('title')->get(['property_id', 'title']);
        $propertyId = $properties->contains('property_id', $request->integer('property')) ? $request->integer('property') : null;
        $category = in_array($request->query('category'), TenantConcern::CATEGORIES, true) ? $request->query('category') : null;
        $status = array_key_exists($request->query('status'), self::FILTERS) ? $request->query('status') : 'active';

        $base = TenantConcern::query()
            ->whereHas('conversation', fn ($q) => $q->where('landlord_id', $landlordId))
            ->when($propertyId, fn ($q) => $q->whereHas('reservation', fn ($r) => $r->where('property_id', $propertyId)))
            ->when($category, fn ($q) => $q->where('category', $category));

        $byStatus = (clone $base)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $counts = [
            'active' => ($byStatus[TenantConcern::OPEN] ?? 0) + ($byStatus[TenantConcern::IN_PROGRESS] ?? 0),
            'Open' => $byStatus[TenantConcern::OPEN] ?? 0,
            'In Progress' => $byStatus[TenantConcern::IN_PROGRESS] ?? 0,
            'Fixed' => $byStatus[TenantConcern::FIXED] ?? 0,
            'all' => $byStatus->sum(),
        ];

        // Where the unresolved ones are: by category (for the chosen property) and by property (for the chosen category).
        $open = TenantConcern::unresolved()
            ->whereHas('conversation', fn ($q) => $q->where('landlord_id', $landlordId))
            ->with('reservation:reservation_id,property_id')
            ->get(['concern_id', 'reservation_id', 'category']);
        $byCategory = $open->when($propertyId, fn ($c) => $c->filter(fn ($x) => $x->reservation?->property_id === $propertyId))
            ->countBy('category')->sortDesc();
        $byProperty = $open->when($category, fn ($c) => $c->where('category', $category))
            ->countBy(fn ($x) => $x->reservation?->property_id)->sortDesc();

        $concerns = $base
            ->when($status === 'active', fn ($q) => $q->unresolved())
            ->when(in_array($status, TenantConcern::STATUSES, true), fn ($q) => $q->where('status', $status))
            ->with(['tenant:user_id,first_name,last_name', 'reservation:reservation_id,property_id,unit_id,rental_status', 'reservation.unit:unit_id,unit_label', 'reservation.property:property_id,title', 'conversation:conversation_id,status'])
            ->orderByRaw("case status when 'Open' then 0 when 'In Progress' then 1 else 2 end")
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('landlord.concerns.index', [
            'concerns' => $concerns,
            'counts' => $counts,
            'status' => $status,
            'filters' => self::FILTERS,
            'properties' => $properties,
            'propertyId' => $propertyId,
            'category' => $category,
            'byCategory' => $byCategory,
            'byProperty' => $byProperty,
        ]);
    }
}
