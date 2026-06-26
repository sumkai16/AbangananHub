<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreVerificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $verification = $user->verificationApplication;

        if ($verification && $verification->isPending()) {
            return false;
        }

        return ! $user->hasRole('Landlord');
    }

    public function rules(): array
    {
        return [
            'government_id' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'government_id.required' => 'Please upload a government-issued ID.',
            'government_id.mimes' => 'ID must be a JPG, PNG, or PDF file.',
            'government_id.max' => 'ID file must be under 5MB.',
        ];
    }
}