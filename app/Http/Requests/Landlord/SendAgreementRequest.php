<?php

namespace App\Http\Requests\Landlord;

use App\Models\Reservation;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Carbon;

/**
 * Sending the rental agreement with the final terms agreed in chat (step 6 of the reservation flow,
 * plans/reservation-final-terms.md). Used by the web forms and the mobile endpoint. Every term is optional so a
 * client that only sends the extra-terms text (older app builds) still issues an agreement, with the inquiry's
 * dates and the listing's rent and deposit.
 */
class SendAgreementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('advanceStatus', $this->route('reservation'));
    }

    public function rules(): array
    {
        $rules = [
            'agreed_monthly_rent' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'security_deposit' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'advance_months' => ['nullable', 'integer', 'min:0', 'max:3'],
            'target_move_in_date' => ['nullable', 'date', 'after_or_equal:today', 'before_or_equal:'.now()->addYear()->toDateString()],
            'target_move_out_date' => ['nullable', 'date'],
            'rent_due_day' => ['nullable', 'integer', 'min:1', 'max:28'],
            'occupants_count' => ['nullable', 'integer', 'min:1', 'max:20'],
            'agreement_terms_notes' => ['nullable', 'string', 'max:2000'],
        ];

        // The web forms carry the landlord's consent checkbox; the mobile endpoint never had one.
        if (! $this->expectsJson()) {
            $rules['accept_tc'] = ['accepted'];
        }

        return $rules;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            /** @var Reservation|null $reservation */
            $reservation = $this->route('reservation');
            $moveIn = $this->input('target_move_in_date') ?: $reservation?->target_move_in_date?->toDateString();
            $moveOut = $this->input('target_move_out_date');

            if ($moveIn && $moveOut && Carbon::parse($moveOut)->lt(Carbon::parse($moveIn)->addMonthNoOverflow())) {
                $v->errors()->add('target_move_out_date', 'The lease runs at least a month. Pick a move-out date on or after '
                    .Carbon::parse($moveIn)->addMonthNoOverflow()->format('M j, Y').'.');
            }

            $limit = $reservation?->unit?->occupancy_limit;
            if ($limit !== null && $this->filled('occupants_count') && (int) $this->input('occupants_count') > (int) $limit) {
                $v->errors()->add('occupants_count', "This unit allows up to {$limit} ".($limit == 1 ? 'person' : 'people').'.');
            }
        });
    }

    /**
     * The web forms sit in a chat action bar and a list modal that are closed again after the redirect, so a field
     * error would be invisible. Say what was wrong through the flash modal instead. JSON clients get the usual 422.
     */
    protected function failedValidation(Validator $validator): void
    {
        if ($this->expectsJson()) {
            parent::failedValidation($validator);
        }

        throw new HttpResponseException(back()->with('error', $validator->errors()->first()));
    }

    public function messages(): array
    {
        return [
            'accept_tc.accepted' => 'You must accept the terms and conditions to proceed.',
            'advance_months.max' => 'Advance rent can be up to 3 months.',
            'rent_due_day.max' => 'Pick a due day between 1 and 28 so it exists in every month.',
            'target_move_in_date.after_or_equal' => 'The move-in date cannot be in the past.',
        ];
    }
}
