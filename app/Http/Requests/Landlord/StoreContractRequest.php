<?php

namespace App\Http\Requests\Landlord;

use App\Models\LandlordTenant;
use App\Support\Phone;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

/**
 * "Add lease" on the Contracts page. The unit, dates, terms, lease file and move-in money follow exactly the
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
            ...($this->addsNewTenant() ? [
                // "New tenant" in the Add a rental modal: the same name/contact rules as Add tenant on the Tenants page.
                'first_name' => ['required', 'string', 'max:100'],
                'last_name' => ['required', 'string', 'max:100'],
                'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')],
                'contact_number' => ['required_without:email', 'nullable', 'string', 'max:20', Phone::RULE],
            ] : [
                // Scoped to this landlord's own, unarchived list so the id cannot point at someone else's tenant.
                'landlord_tenant_id' => ['required', 'integer', Rule::exists('landlord_tenants', 'landlord_tenant_id')
                    ->where('landlord_id', $this->user()->user_id)
                    ->whereNull('archived_at')],
            ]),
            ...$rules,
            // The end date is optional: left blank, the rental is open-ended (no end, no renewal, rent keeps running).
            'move_out_date' => ['nullable', 'date', 'after:move_in_date'],
            'agreement_terms_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /** True when the modal's "New tenant" side was used: the tenant is created in the same submit. */
    public function addsNewTenant(): bool
    {
        return $this->input('tenant_mode') === 'new';
    }

    public function withValidator(Validator $validator): void
    {
        parent::withValidator($validator);

        // A number already on this landlord's list is the same person: send them to "Existing tenant" instead of
        // creating a second record. Only this landlord's own list is checked, never other landlords' tenants.
        $validator->after(function (Validator $validator) {
            if (! $this->addsNewTenant() || $validator->errors()->hasAny(['contact_number', 'email']) || ! $this->filled('contact_number')) {
                return;
            }

            $typed = Phone::digits($this->input('contact_number'));
            $match = LandlordTenant::where('landlord_id', $this->user()->user_id)->visible()->with('tenant')->get()
                ->first(fn (LandlordTenant $row) => $typed !== '' && Phone::digits($row->tenant?->contact_number) === $typed);

            if ($match) {
                $validator->errors()->add('contact_number', 'This number is already on your list as '.$match->code().' · '
                    .trim($match->tenant->first_name.' '.$match->tenant->last_name).'. Choose Existing tenant instead.');
                // Lets the modal offer a one-tap switch to that tenant after the redirect back.
                $this->session()->flash('duplicate_tenant_id', $match->landlord_tenant_id);
            }
        });
    }

    public function messages(): array
    {
        return [
            ...parent::messages(),
            'first_name.required' => 'Enter the tenant’s first name.',
            'last_name.required' => 'Enter the tenant’s last name.',
            'email.unique' => 'This email already has an AbangananHub account. Ask the tenant to book the unit online, or leave the email blank.',
            'contact_number.required_without' => 'Enter a mobile number. Without an email there is no other way to reach this tenant.',
            'landlord_tenant_id.required' => 'Choose the tenant this rental is for.',
            'landlord_tenant_id.exists' => 'Choose a tenant from your list.',
        ];
    }
}
