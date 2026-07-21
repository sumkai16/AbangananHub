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
            'target_move_in_date'  => 'required|date|after_or_equal:today|before_or_equal:' . now()->addYear()->toDateString(),
            'target_move_out_date' => 'nullable|date|after:target_move_in_date',
            'message'              => 'nullable|string|max:300',
        ];
    }

    public function messages(): array
    {
        return [
            'unit_id.required'                    => 'Please select a unit before sending your inquiry.',
            'unit_id.exists'                      => 'That unit no longer exists.',
            'target_move_in_date.required'        => 'Please choose your target move-in date.',
            'target_move_in_date.after_or_equal'  => 'Your move-in date cannot be in the past.',
            'target_move_in_date.before_or_equal' => 'Please choose a move-in date within the next year.',
            'target_move_out_date.after'          => 'Your move-out date must be after your move-in date.',
            'message.max'                         => 'Your message cannot be longer than 300 characters.',
        ];
    }
}
