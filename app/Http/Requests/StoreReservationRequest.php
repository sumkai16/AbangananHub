<?php

namespace App\Http\Requests;

use App\Models\PropertyUnit;
use App\Support\BusinessTime;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

/**
 * A tenant's inquiry (step 2 of the reservation flow, plans/reservation-final-terms.md): only the unit is needed. When
 * they want to move in, when they expect to leave and how many will live there are optional, because someone still
 * planning may only want to ask a question first (plans/tenant-feedback-nov-5.md). The landlord sets the final dates
 * and terms in the agreement, and cannot send one until a move-in date exists.
 */
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
            // Optional: a tenant still planning can leave it. When given it is read in business time, like every other
            // date rule. The agreement's start, the rent due day and its defaults come from it, so the landlord sets it
            // there if the tenant did not.
            'target_move_in_date'  => 'nullable|date|after_or_equal:' . BusinessTime::today() . '|before_or_equal:' . BusinessTime::now()->addYear()->toDateString(),
            // Optional, and only with a move-in date. When given, at least a month after it (checked in
            // withValidator), because rent and the ledger are monthly.
            'target_move_out_date' => 'nullable|date|after:target_move_in_date',
            'occupants_count'      => 'nullable|integer|min:1|max:20',
            'message'              => 'nullable|string|max:300',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            // A move-out date means nothing without a move-in date to measure the stay from.
            if ($this->filled('target_move_out_date') && ! $this->filled('target_move_in_date') && ! $v->errors()->has('target_move_out_date')) {
                $v->errors()->add('target_move_out_date', 'Pick a move-in date first, or leave both blank.');
            }

            self::checkStayAndOccupants(
                $v, $this->input('unit_id'), $this->input('target_move_in_date'), $this->input('target_move_out_date'), $this->input('occupants_count'),
            );
        });
    }

    /**
     * The two checks that need a second field: a stay of at least one month, and no more occupants than the unit
     * allows. Shared with the mobile inquiry endpoint so the app and the web refuse the same things.
     */
    public static function checkStayAndOccupants(Validator $v, $unitId, $moveIn, $moveOut, $occupants): void
    {
        if ($moveIn && $moveOut && ! $v->errors()->has('target_move_out_date')) {
            $minimum = Carbon::parse($moveIn)->addMonthNoOverflow();

            if (Carbon::parse($moveOut)->lt($minimum)) {
                $v->errors()->add('target_move_out_date', 'Stays are monthly. Pick a move-out date on or after '.$minimum->format('M j, Y').', or leave it blank.');
            }
        }

        if ($occupants !== null && $unitId) {
            $limit = PropertyUnit::where('unit_id', $unitId)->value('occupancy_limit');

            if ($limit !== null && (int) $occupants > (int) $limit) {
                $v->errors()->add('occupants_count', "This unit allows up to {$limit} ".($limit == 1 ? 'person' : 'people').'.');
            }
        }
    }

    public function messages(): array
    {
        return [
            'unit_id.required'                    => 'Please select a unit before sending your inquiry.',
            'unit_id.exists'                      => 'That unit no longer exists.',
            'target_move_in_date.after_or_equal'  => 'Your move-in date cannot be in the past.',
            'target_move_in_date.before_or_equal' => 'Please choose a move-in date within the next year.',
            'target_move_out_date.after'          => 'The move-out date must be after the move-in date.',
            'occupants_count.min'                 => 'At least one person will live there.',
            'message.max'                         => 'Your message cannot be longer than 300 characters.',
        ];
    }
}
