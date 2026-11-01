<?php

namespace App\Http\Controllers\Landlord;

use App\Http\Controllers\Controller;
use App\Http\Requests\Landlord\LeaseTemplateRequest;
use App\Models\LeaseTemplate;
use App\Models\Property;
use App\Models\PropertyUnit;
use App\Models\Reservation;
use App\Models\User;
use App\Support\LeaseBody;
use App\Support\LeaseTerms;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * The Leases page (plans/lease-templates-page.md): the landlord's lease template library and where each
 * template is used. Sent and signed leases are frozen copies on the reservation and are never changed here.
 */
class LeaseTemplateController extends Controller
{
    public function index(Request $request)
    {
        $landlord = $request->user();

        // The Sent & signed tab was the Contracts list again; its rows now live on the Tenants page. An unsigned one
        // (the old "Awaiting signature") belongs to the Reservations pipeline.
        if ($request->query('tab') === 'sent') {
            return $request->query('status') === 'awaiting'
                ? redirect()->route('landlord.reservations.index', ['stage' => 'agreement'])
                : redirect()->route('landlord.tenants.index', array_filter($request->only(['search', 'property'])));
        }

        $templates = LeaseTemplate::where('landlord_id', $landlord->user_id)
            ->withCount(['properties', 'units'])
            ->orderBy('name')
            ->get();

        $properties = Property::where('landlord_id', $landlord->user_id)
            ->with(['units' => fn ($q) => $q->orderBy('unit_label')->select('unit_id', 'property_id', 'unit_label', 'lease_template_id')])
            ->orderBy('title')
            ->get(['property_id', 'landlord_id', 'title', 'property_type', 'lease_template_id']);

        $selected = $properties->firstWhere('property_id', $request->integer('property')) ?? $properties->first();

        return view('landlord.leases.index', compact('templates', 'properties', 'selected'));
    }

    public function create(Request $request)
    {
        $source = $request->filled('from')
            ? LeaseTemplate::where('landlord_id', $request->user()->user_id)->find($request->integer('from'))
            : null;

        // A new lease starts from a starter for a property type (or a blank page); a duplicate starts from its source.
        $starter = $source ? null : (array_key_exists($request->query('starter'), LeaseBody::STARTERS) ? $request->query('starter') : 'standard');

        $template = $source
            ? $source->replicate(['properties_count', 'units_count'])
            : new LeaseTemplate(['body' => LeaseBody::starter($starter)]);
        $template->name = $source ? 'Copy of '.$source->name : ($starter === 'standard' || $starter === 'blank' ? '' : LeaseBody::STARTERS[$starter].' policy');
        $template->version = 1;

        return view('landlord.leases.edit', [
            'template' => $template,
            'properties' => $this->ownProperties($request->user()),
            'starter' => $starter,
        ]);
    }

    public function store(LeaseTemplateRequest $request)
    {
        $template = new LeaseTemplate($request->templateAttributes());
        $template->landlord_id = $request->user()->user_id;
        $template->save();
        $this->syncProperties($request, $template);

        return redirect()->route('landlord.lease-templates.edit', $template)
            ->with('success', 'Policy saved. Choose which properties and units use it on the Lease policies page.');
    }

    public function edit(Request $request, LeaseTemplate $leaseTemplate)
    {
        Gate::authorize('manage', $leaseTemplate);

        $leaseTemplate->loadCount(['properties', 'units']);

        return view('landlord.leases.edit', ['template' => $leaseTemplate, 'properties' => $this->ownProperties($request->user()), 'starter' => null]);
    }

    public function update(LeaseTemplateRequest $request, LeaseTemplate $leaseTemplate)
    {
        Gate::authorize('manage', $leaseTemplate);

        $leaseTemplate->fill($request->templateAttributes())->saveWithVersion();
        $this->syncProperties($request, $leaseTemplate);

        return back()->with('success', 'Policy saved (version '.$leaseTemplate->version.'). Agreements you send from now on use it; agreements already sent do not change.');
    }

    public function destroy(LeaseTemplate $leaseTemplate)
    {
        Gate::authorize('manage', $leaseTemplate);

        // The foreign keys are nullOnDelete: properties and units on this lease go back to the standard one.
        $leaseTemplate->delete();

        return redirect()->route('landlord.leases.index')
            ->with('success', '"'.$leaseTemplate->name.'" deleted. Places that used it now send the standard policy.');
    }

    /** Point a property or a unit at a template, or back to the default (null). */
    public function assign(Request $request)
    {
        $landlordId = $request->user()->user_id;

        $data = $request->validate([
            'target' => ['required', Rule::in(['property', 'unit'])],
            'id' => ['required', 'integer'],
            'lease_template_id' => ['nullable', 'integer', Rule::exists('lease_templates', 'lease_template_id')->where('landlord_id', $landlordId)],
        ]);

        $model = $data['target'] === 'property'
            ? Property::where('landlord_id', $landlordId)->findOrFail($data['id'])
            : PropertyUnit::whereHas('property', fn ($q) => $q->where('landlord_id', $landlordId))->findOrFail($data['id']);

        $model->forceFill(['lease_template_id' => $data['lease_template_id'] ?? null])->save();

        return back()->with('success', 'Policy updated. It applies to agreements you send from now on.');
    }

    /**
     * The template as a tenant would read it, filled in with one of the landlord's properties and a sample tenant.
     * Nothing is saved: the reservation, tenant and template here are unsaved models.
     */
    public function preview(LeaseTemplateRequest $request)
    {
        $data = $request->validate([
            'property_id' => ['required', 'integer', Rule::exists('properties', 'property_id')->where('landlord_id', $request->user()->user_id)],
        ]);

        $property = Property::with(['landlord', 'units'])->findOrFail($data['property_id']);
        $unit = $property->units->sortBy('unit_label')->first();

        $reservation = new Reservation([
            'property_id' => $property->property_id,
            'target_move_in_date' => today()->addWeek(),
            'agreed_monthly_rent' => $unit?->rental_fee,
            'rent_due_day' => today()->addWeek()->day,
        ]);
        $reservation->reservation_id = 0;
        $reservation->created_at = now();
        $reservation->setRelation('property', $property);
        $reservation->setRelation('unit', $unit);
        $reservation->setRelation('tenant', new User(['first_name' => 'Sample', 'last_name' => 'Tenant']));

        $template = new LeaseTemplate($request->templateAttributes());

        return response()->json([
            'html' => view('leases._document', ['terms' => LeaseTerms::snapshot($reservation, template: $template)])->render(),
        ]);
    }

    private function ownProperties(User $landlord)
    {
        return Property::where('landlord_id', $landlord->user_id)
            ->with('leaseTemplate:lease_template_id,name')
            ->orderBy('title')
            ->get(['property_id', 'title', 'property_type', 'house_rules', 'lease_template_id']);
    }

    /**
     * The "Use this lease for" checklist on the template page: ticked properties use this template, unticked ones
     * that used it go back to the standard lease. Units keep their own choice. Only runs when the form sent the list.
     */
    private function syncProperties(LeaseTemplateRequest $request, LeaseTemplate $template): void
    {
        if (! $request->boolean('assign_present')) {
            return;
        }

        $ids = collect($request->validated('assigned_properties', []))->map(fn ($id) => (int) $id);
        $own = Property::where('landlord_id', $request->user()->user_id);

        (clone $own)->whereIn('property_id', $ids)->update(['lease_template_id' => $template->lease_template_id]);
        (clone $own)->where('lease_template_id', $template->lease_template_id)->whereNotIn('property_id', $ids)->update(['lease_template_id' => null]);
    }
}
