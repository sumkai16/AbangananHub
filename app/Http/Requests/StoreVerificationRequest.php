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
            'id_type'          => ['required', 'string', 'in:PhilSys,Professional ID Card,Driver\'s License,Passport,UMID,Postal ID,SSS ID'],
            'id_image'         => ['required', 'string'], // base64 from camera (front)
            'id_back'          => ['required_unless:id_type,Passport', 'nullable', 'string'], // base64 from camera (back)
            'selfie'           => ['required', 'string'], // base64 from camera
            'business_name'    => ['required', 'string', 'max:255'],
            'description'      => ['nullable', 'string', 'max:1000'],
            'logo'             => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
            'contact_number'   => ['required', 'string', 'max:20'],
            'business_address' => ['required', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'id_type.required'          => 'Please select your ID type.',
            'id_type.in'                => 'Please select a valid ID type.',
            'id_image.required'         => 'Please capture a photo of the front of your government ID.',
            'id_back.required_unless'   => 'Please capture a photo of the back of your government ID.',
            'selfie.required'           => 'Please take a selfie for identity verification.',
            'business_name.required'    => 'Please enter your rental business name.',
            'logo.image'                => 'Logo must be an image file.',
            'logo.max'                  => 'Logo file must be under 2MB.',
            'contact_number.required'   => 'Please provide a contact number.',
            'business_address.required' => 'Please provide a business address.',
        ];
    }
}