<?php

namespace App\Http\Requests\Landlord;

use App\Models\PropertyDocument;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePropertyDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'document_type'   => ['required', 'string', Rule::in(PropertyDocument::TYPES)],
            'file'            => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
            'document_number' => ['nullable', 'string', 'max:100'],
            'expiry_date'     => ['nullable', 'date', 'after:today'],
        ];
    }
}
