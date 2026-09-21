<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

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
            // Contacting a landlord is a single action now — the old
            // Inquiry/Reserve toggle produced the same rental_status either
            // way, so the two-mode form was explaining a distinction that
            // didn't exist. A move-in date is optional either way: a tenant
            // still asking questions hasn't necessarily settled on one.
            'target_move_in_date'  => 'nullable|date|after_or_equal:today|before_or_equal:' . now()->addYear()->toDateString(),
            // The app is monthly-only end to end (rent, the ledger, every
            // price label) — duration is expressed in months, not a
            // free-picked move-out date, so a tenant can't accidentally
            // request a two-day stay. Null means open-ended.
            'duration_months'      => 'nullable|integer|in:1,3,6,12',
            'target_move_out_date' => 'nullable|date|after:target_move_in_date',
            'message'              => 'nullable|string|max:300',
        ];
    }

    public function messages(): array
    {
        return [
            'unit_id.required'                    => 'Please select a unit before sending your inquiry.',
            'unit_id.exists'                      => 'That unit no longer exists.',
            'target_move_in_date.after_or_equal'  => 'Your move-in date cannot be in the past.',
            'target_move_in_date.before_or_equal' => 'Please choose a move-in date within the next year.',
            'duration_months.in'                  => 'Please choose a valid stay length.',
            'message.max'                         => 'Your message cannot be longer than 300 characters.',
        ];
    }

    /**
     * target_move_out_date is derived server-side from move-in + duration,
     * never taken from the client directly — the form no longer renders a
     * move-out picker, only a duration select. Recomputing it here (rather
     * than trusting whatever the client posts) is what keeps a tampered or
     * stale move-out value from reaching the reservation.
     */
    protected function prepareForValidation(): void
    {
        $moveIn = $this->input('target_move_in_date');
        $months = $this->input('duration_months');

        $moveOut = ($moveIn && $months)
            ? Carbon::parse($moveIn)->addMonthsNoOverflow((int) $months)->toDateString()
            : null;

        $this->merge(['target_move_out_date' => $moveOut]);
    }
}
