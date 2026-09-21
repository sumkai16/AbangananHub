<?php

namespace App\Http\Requests\Landlord;

use App\Rules\WithinCebu;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePropertyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title'             => ['required', 'string', 'min:10', 'max:150'],
            'description'       => ['required', 'string', 'min:20', 'max:3000'],
            'property_type'     => ['required', 'in:Bedspace,Room,Apartment,House'],
            'living_arrangement' => ['nullable', 'in:Private,Shared,Mixed,Female only,Male only,Couples allowed,Family-friendly'],
            'address'           => ['required', 'string', 'min:10', 'max:255'],
            'city_municipality' => ['required', 'string', Rule::in(config('cebu.lgus'))],
            'barangay'          => ['nullable', 'string', 'max:100'],
            'latitude'          => ['required', 'numeric', 'between:-90,90', new WithinCebu($this->input('longitude'))],
            'longitude'         => ['required', 'numeric', 'between:-180,180'],
            'photos'            => ['required', 'array', 'min:1', 'max:10'],
            'photos.*'          => ['image', 'mimes:jpeg,png,jpg,webp', 'max:5120'],
            'amenities'         => ['nullable', 'array'],
            'amenities.*'       => ['integer', 'exists:amenities,amenity_id'],
        ];
    }

    public function messages(): array
    {
        return [
            'city_municipality.in' => 'Choose a city or municipality within Cebu.',
        ];
    }
}
