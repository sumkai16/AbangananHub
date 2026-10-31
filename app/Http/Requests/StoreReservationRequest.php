<?php

namespace App\Http\Requests;

use App\Models\PropertyUnit;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

/**
 * A tenant's inquiry (step 2 of the reservation flow, plans/reservation-final-terms.md): when they want to move in,
 * when they expect to leave if they know, and how many will live there. The landlord sets the final dates and terms
 * in the agreement; these are the tenant's ask.
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
            'target_move_in_date'  => 'required|date|after_or_equal:today|before_or_equal:'.now()->addYear()->toDateString(),
            // Optional: a tenant still deciding can say so. When given, at least a month after move-in (checked in
            // withValidator), because rent and the ledger are monthly.
            'target_move_out_date' => 'nullable|date|after:target_move_in_date',
            'occupants_count'      => 'required|integer|min:1|max:20',
            'message'              => 'nullable|string|max:300',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(fn (Validator $v) => self::checkStayAndOccupants(
            $v, $this->input('unit_id'), $this->input('target_move_in_date'), $this->input('target_move_out_date'), $this->input('occupants_count'),
        ));
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
            'target_move_in_date.required'        => 'Choose when you would like to move in.',
            'target_move_in_date.after_or_equal'  => 'Your move-in date cannot be in the past.',
            'target_move_in_date.before_or_equal' => 'Please choose a move-in date within the next year.',
            'target_move_out_date.after'          => 'The move-out date must be after the move-in date.',
            'occupants_count.required'            => 'Tell the landlord how many people will live there.',
            'occupants_count.min'                 => 'At least one person will live there.',
            'message.max'                         => 'Your message cannot be longer than 300 characters.',
        ];
    }
}
