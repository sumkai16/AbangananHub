<?php

namespace App\Http\Requests\Landlord;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWalkInTenantRequest extends FormRequest
{
    /**
     * The route already sits behind the `landlord` middleware. Ownership of the
     * specific unit is not checkable here — it has to be re-read under a lock
     * in the controller anyway, so it is asserted there rather than twice.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            // An existing walk-in this landlord already recorded, for a tenant
            // moving between units. Scoped to their own walk-ins so this can't
            // be pointed at an arbitrary user id.
            'existing_tenant_id' => ['nullable', 'integer', Rule::exists('users', 'user_id')
                ->where('is_walk_in', true)
                ->where('created_by_landlord_id', $this->user()->user_id)],

            'first_name'     => ['required_without:existing_tenant_id', 'nullable', 'string', 'max:100'],
            'last_name'      => ['required_without:existing_tenant_id', 'nullable', 'string', 'max:100'],
            // Nullable because a walk-in often has no email at all; unique when
            // given so it can never collide with a registered account.
            'email'          => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')],
            // Required when there is no email: a tenant the landlord cannot
            // reach by either channel is a record with no way to follow up.
            'contact_number' => ['required_without_all:email,existing_tenant_id', 'nullable', 'string', 'max:20'],

            'unit_id'              => ['required', 'integer', 'exists:property_units,unit_id'],
            'move_in_date'         => ['required', 'date', 'before_or_equal:' . now()->addYear()->toDateString()],
            'move_out_date'        => ['nullable', 'date', 'after:move_in_date'],
            'occupants_count'      => ['nullable', 'integer', 'min:1', 'max:20'],
            'agreed_monthly_rent'  => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'rent_due_day'         => ['nullable', 'integer', 'min:1', 'max:28'],
            'notes'                => ['nullable', 'string', 'max:1000'],

            // Optional move-in money (deposit, advance) collected at the door.
            'initial_amount'       => ['nullable', 'numeric', 'min:1', 'max:1000000'],
            'initial_type'         => ['required_with:initial_amount', 'nullable', Rule::in(['Initial', 'Deposit'])],
            'payment_method'       => ['required_with:initial_amount', 'nullable', Rule::in(['Cash', 'GCash', 'Bank Transfer', 'Maya', 'Check', 'Other'])],
            'payment_date'         => ['nullable', 'date', 'before_or_equal:today'],
            'reference_no'         => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'first_name.required_without'     => 'Enter the tenant’s first name.',
            'last_name.required_without'      => 'Enter the tenant’s last name.',
            'email.unique'                    => 'Someone already uses that email address on AbangananHub.',
            'contact_number.required_without_all' => 'Enter a contact number — without an email there is no other way to reach this tenant.',
            'unit_id.required'                => 'Choose the unit this tenant is moving into.',
            'move_in_date.required'           => 'Enter the date the tenant moved in.',
            'move_out_date.after'             => 'The move-out date must be after the move-in date.',
            'rent_due_day.max'                => 'Pick a due day between 1 and 28 so it exists in every month.',
            'initial_type.required_with'      => 'Choose what the initial payment was for.',
            'payment_method.required_with'    => 'Choose how the initial payment was made.',
            'payment_date.before_or_equal'    => 'A payment cannot be recorded for a future date.',
        ];
    }

    /**
     * A blank email posts as '' and would fail the email rule instead of being
     * treated as absent — and an empty string is not what belongs in a nullable
     * unique column either.
     */
    protected function prepareForValidation(): void
    {
        foreach (['email', 'contact_number', 'reference_no', 'existing_tenant_id'] as $field) {
            if ($this->input($field) === '') {
                $this->merge([$field => null]);
            }
        }
    }
}
