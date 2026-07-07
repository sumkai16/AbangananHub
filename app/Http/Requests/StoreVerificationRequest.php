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
            'business_name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
            'contact_number' => ['required', 'string', 'max:20'],
            'business_address' => ['required', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'government_id.required' => 'Please upload a government-issued ID.',
            'government_id.mimes' => 'ID must be a JPG, PNG, or PDF file.',
            'government_id.max' => 'ID file must be under 5MB.',
            'business_name.required' => 'Please enter your rental business name.',
            'logo.image' => 'Logo must be an image file.',
            'logo.max' => 'Logo file must be under 2MB.',
            'contact_number.required' => 'Please provide a contact number.',
            'business_address.required' => 'Please provide a business address.',
        ];
    }
}