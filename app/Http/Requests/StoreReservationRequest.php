<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'unit_id'              => 'required|integer|exists:property_units,unit_id',
            // An inquiry is a question, not a commitment — the tenant is asking
            // before they know whether they want the unit, so demanding a
            // move-in date is asking them to guess. Reserving is the point at
            // which naming a date means something.
            'mode'                 => 'required|in:inquiry,reserve',
            'target_move_in_date'  => 'required_if:mode,reserve|nullable|date|after_or_equal:today|before_or_equal:' . now()->addYear()->toDateString(),
            'target_move_out_date' => 'nullable|date|after:target_move_in_date',
            'message'              => 'nullable|string|max:300',
        ];
    }

    public function messages(): array
    {
        return [
            'unit_id.required'                    => 'Please select a unit before sending your inquiry.',
            'unit_id.exists'                      => 'That unit no longer exists.',
            'mode.required'                       => 'Choose whether you are inquiring or reserving.',
            'mode.in'                             => 'Choose whether you are inquiring or reserving.',
            'target_move_in_date.required_if'     => 'Please choose your target move-in date to reserve.',
            'target_move_in_date.after_or_equal'  => 'Your move-in date cannot be in the past.',
            'target_move_in_date.before_or_equal' => 'Please choose a move-in date within the next year.',
            'target_move_out_date.after'          => 'Your move-out date must be after your move-in date.',
            'message.max'                         => 'Your message cannot be longer than 300 characters.',
        ];
    }

    /**
     * Dates typed while the Reserve tab was open then abandoned for Inquiry
     * would otherwise still post, silently attaching a commitment the tenant
     * backed out of — and re-arming the Clock 1 basis this change exists to
     * stop relying on.
     */
    protected function prepareForValidation(): void
    {
        if ($this->input('mode') === 'inquiry') {
            $this->merge([
                'target_move_in_date'  => null,
                'target_move_out_date' => null,
            ]);
        }
    }
}
