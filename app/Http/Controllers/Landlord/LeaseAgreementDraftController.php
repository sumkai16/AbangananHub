<?php

namespace App\Http\Controllers\Landlord;

use App\Http\Controllers\Controller;
use App\Models\LandlordTenant;
use App\Models\Property;
use App\Models\Reservation;
use App\Models\User;
use App\Support\LeaseTemplates;
use App\Support\LeaseTerms;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * "Generate a rental agreement" on the Rental policies page: type a tenant's name (or pick one from the list), choose a
 * property and unit, and get the printable agreement, worded by the unit's policy. Nothing is saved: the reservation and
 * tenant here are unsaved models, the same trick as the policy preview. To track the rental, use Add rental.
 */
class LeaseAgreementDraftController extends Controller
{
    public function show(Request $request)
    {
        $landlord = $request->user();

        $properties = Property::where('landlord_id', $landlord->user_id)
            ->with(['leaseTemplate', 'units' => fn ($q) => $q->with('leaseTemplate')->orderBy('unit_label')])
            ->orderBy('title')
            ->get()
            ->filter(fn (Property $p) => $p->units->isNotEmpty())
            ->values();

        $tenants = LandlordTenant::where('landlord_id', $landlord->user_id)->visible()
            ->with('tenant:user_id,first_name,last_name,email,contact_number')
            ->get()
            ->filter(fn (LandlordTenant $row) => $row->tenant)
            ->map(fn (LandlordTenant $row) => [
                'id' => $row->landlord_tenant_id,
                'name' => trim($row->tenant->first_name.' '.$row->tenant->last_name),
                'code' => $row->code(),
            ])
            ->sortBy('name')
            ->values();

        // Which policy words each unit's agreement (the unit's own, else the property's, else the standard one).
        $policyNames = $properties->flatMap(fn (Property $p) => $p->units->mapWithKeys(
            fn ($u) => [(string) $u->unit_id => LeaseTemplates::for($p, $u)?->name ?? 'Standard policy']
        ));

        $terms = null;
        if ($request->filled('property_id')) {
            $data = $request->validate([
                'tenant_name' => ['required', 'string', 'max:150'],
                'landlord_tenant_id' => ['nullable', 'integer', Rule::exists('landlord_tenants', 'landlord_tenant_id')->where('landlord_id', $landlord->user_id)],
                'property_id' => ['required', 'integer', Rule::exists('properties', 'property_id')->where('landlord_id', $landlord->user_id)],
                'unit_id' => ['required', 'integer', Rule::exists('property_units', 'unit_id')->where('property_id', $request->input('property_id'))],
                'move_in_date' => ['required', 'date'],
                'move_out_date' => ['nullable', 'date', 'after:move_in_date'],
                'rent' => ['nullable', 'numeric', 'min:0'],
                'rent_due_day' => ['nullable', 'integer', 'between:1,28'],
                'occupants' => ['nullable', 'integer', 'min:1', 'max:50'],
                'extra_terms' => ['nullable', 'string', 'max:2000'],
            ]);

            $property = $properties->firstWhere('property_id', (int) $data['property_id']);
            $unit = $property->units->firstWhere('unit_id', (int) $data['unit_id']);

            // A tenant picked from the list brings their contact details; a typed name is just a name.
            $picked = filled($data['landlord_tenant_id'] ?? null)
                ? LandlordTenant::with('tenant')->find($data['landlord_tenant_id'])?->tenant
                : null;
            $tenant = $picked ?? new User(['first_name' => trim($data['tenant_name']), 'last_name' => '']);

            $reservation = (new Reservation)->forceFill([
                'property_id' => $property->property_id,
                'target_move_in_date' => $data['move_in_date'],
                'target_move_out_date' => $data['move_out_date'] ?? null,
                'agreed_monthly_rent' => $data['rent'] ?? $unit->rental_fee,
                'rent_due_day' => $data['rent_due_day'] ?? null,
                'occupants_count' => $data['occupants'] ?? null,
                'agreement_terms_notes' => $data['extra_terms'] ?? null,
            ]);
            $reservation->reservation_id = 0;
            $reservation->created_at = now();
            $reservation->setRelation('property', $property->setRelation('landlord', $landlord));
            $reservation->setRelation('unit', $unit);
            $reservation->setRelation('tenant', $tenant);

            $terms = LeaseTerms::snapshot(
                $reservation,
                template: LeaseTemplates::forReservation($reservation),
                overrides: ['reference' => 'AGR-'.now()->format('Ymd').'-U'.$unit->unit_id],
            );
        }

        return view('landlord.leases.agreement', [
            'properties' => $properties,
            'tenants' => $tenants,
            'terms' => $terms,
            'policyNames' => $policyNames->all(),
        ]);
    }
}
