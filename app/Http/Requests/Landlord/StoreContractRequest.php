<?php

namespace App\Http\Requests\Landlord;

use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

/**
 * "Add contract" on the Contracts page. The unit, dates, terms, lease file and move-in money follow exactly the
 * walk-in rules (same minimum stay, occupancy limit, payment-required-once-moved-in), so this extends that request
 * and only swaps the tenant fields for a tenant already on the landlord's list.
 */
class StoreContractRequest extends StoreWalkInTenantRequest
{
    public function rules(): array
    {
        $rules = Arr::except(parent::rules(), [
            'existing_tenant_id', 'first_name', 'last_name', 'email', 'contact_number',
            'emergency_contact_name', 'emergency_contact_relationship', 'emergency_contact_number', 'id_type', 'id_number',
        ]);

        return [
            // Scoped to this landlord's own, unarchived list so the id cannot point at someone else's tenant.
            'landlord_tenant_id' => ['required', 'integer', Rule::exists('landlord_tenants', 'landlord_tenant_id')
                ->where('landlord_id', $this->user()->user_id)
                ->whereNull('archived_at')],
            ...$rules,
            // A contract states its end; the walk-in form allowed an open-ended stay.
            'move_out_date' => ['required', 'date', 'after:move_in_date'],
            'agreement_terms_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            ...parent::messages(),
            'landlord_tenant_id.required' => 'Choose the tenant this contract is for.',
            'landlord_tenant_id.exists' => 'Choose a tenant from your list.',
            'move_out_date.required' => 'Enter the date the contract ends.',
        ];
    }
}
