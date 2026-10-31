<?php

namespace App\Http\Requests\Landlord;

use App\Models\TenantDetail;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Add Tenant and Edit tenant on the Tenants page. Name and a way to reach them are required; the emergency contact
 * and ID are optional here (a landlord may not have them yet) and, on edit, a blank field keeps what is saved.
 */
class TenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $editing = $this->route('landlordTenant')?->tenant_id;

        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            // Unique across all users: an email that already has an account is someone else's login. They book
            // online instead, so a landlord can never attach a stranger's account to their list.
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($editing, 'user_id')],
            'contact_number' => ['required_without:email', 'nullable', 'string', 'max:20'],
            ...TenantDetail::rules(false),
        ];
    }

    public function messages(): array
    {
        return [
            'first_name.required' => 'Enter the tenant’s first name.',
            'last_name.required' => 'Enter the tenant’s last name.',
            'email.unique' => 'This email already has an AbangananHub account. Ask the tenant to book the unit online, or leave the email blank.',
            'contact_number.required_without' => 'Enter a mobile number. Without an email there is no other way to reach this tenant.',
            ...TenantDetail::messages(),
        ];
    }

    protected function prepareForValidation(): void
    {
        foreach (['email', 'contact_number'] as $field) {
            if ($this->input($field) === '') {
                $this->merge([$field => null]);
            }
        }
    }
}
