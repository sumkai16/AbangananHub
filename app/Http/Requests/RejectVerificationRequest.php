<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RejectVerificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasRole('Admin');
    }

    public function rules(): array
    {
        return [
            'admin_notes' => ['required', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'admin_notes.required' => 'A reason is required when rejecting an application.',
        ];
    }
}