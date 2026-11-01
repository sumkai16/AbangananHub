<?php

namespace App\Http\Requests\Landlord;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Carbon;

/**
 * Renewing a contract: a later end date, the rent from a chosen month on, and that month. The month can never be
 * before this one, so a renewal never rewrites rent that was already billed.
 */
class RenewContractRequest extends FormRequest
{
    /** Owner only, checked before the rules so another landlord gets a 403, not a validation message. */
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('viewTenancy', $this->route('reservation'));
    }

    public function rules(): array
    {
        return [
            'new_move_out_date' => ['required', 'date', 'before_or_equal:'.now()->addYears(5)->toDateString()],
            'new_monthly_rent' => ['required', 'numeric', 'min:0', 'max:1000000'],
            // A month, posted as Y-m from the form.
            'rent_effective_from' => ['required', 'date_format:Y-m'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $reservation = $this->route('reservation');
            $end = $this->input('new_move_out_date');
            $month = $this->input('rent_effective_from');

            if ($end && $reservation?->target_move_out_date && Carbon::parse($end)->lte($reservation->target_move_out_date)) {
                $validator->errors()->add('new_move_out_date', 'The new end date must be after the current one ('.$reservation->target_move_out_date->format('M j, Y').').');
            }

            if (! $month || $validator->errors()->has('rent_effective_from')) {
                return;
            }

            $from = Carbon::createFromFormat('Y-m', $month)->startOfMonth();

            if ($from->lt(now()->startOfMonth())) {
                $validator->errors()->add('rent_effective_from', 'The new rent cannot start in a past month. Pick this month or later.');
            }

            if ($end && $from->gt(Carbon::parse($end)->startOfMonth())) {
                $validator->errors()->add('rent_effective_from', 'The new rent has to start before the new end date.');
            }
        });
    }

    /**
     * The form lives in a modal whose body is fetched after the page loads, so field errors flashed to the session
     * would be gone by then. Reopen the contract and say what was wrong through the flash modal instead.
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            redirect()->route('landlord.contracts.index', ['open' => $this->route('reservation')?->reservation_id])
                ->with('error', $validator->errors()->first())
        );
    }

    public function messages(): array
    {
        return [
            'new_move_out_date.required' => 'Enter the new end date.',
            'new_monthly_rent.required' => 'Enter the rent, even if it stays the same.',
            'rent_effective_from.required' => 'Choose the month the new rent starts.',
        ];
    }
}
