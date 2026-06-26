<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reservation_date' => ['required', 'date', 'after_or_equal:today'],
            'duration_of_stay' => ['required', 'string', 'in:1-3 Months,6 Months,1 Year,Long Term (1+ Years)'],
            'occupants_count'  => ['required', 'integer', 'min:1'],
            'remarks'          => ['nullable', 'string', 'max:1000'],
        ];
    }
}