<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class RejectPropertyDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasRole('Admin');
    }

    public function rules(): array
    {
        return [
            'rejection_reason' => ['required', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'rejection_reason.required' => 'A reason is required when rejecting a document.',
        ];
    }
}
