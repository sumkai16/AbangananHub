<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Support\NameLock;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        NameLock::apply($this, $this->user());
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => [
                'bail',
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class, 'email')->ignore($this->user()->user_id, 'user_id'),
            ],
            'contact_number' => ['nullable', 'string', 'max:20', \App\Support\Phone::RULE],
            'bio' => ['nullable', 'string', 'max:1000'],
            'profile_visibility' => ['nullable', Rule::in(['public', 'landlords_only', 'private'])],
            // Changing the email changes where password resets go, so a stolen session alone must not be enough.
            'current_password' => $this->changesEmail() ? ['bail', 'required', 'string', 'current_password'] : ['nullable'],
        ];
    }

    public function messages(): array
    {
        return [
            'current_password.required' => 'Enter your current password to change your email address.',
            'current_password.current_password' => 'That password is not right.',
        ];
    }

    private function changesEmail(): bool
    {
        $email = $this->input('email');

        // Hostile input (an array) is not "the same email"; the email rule rejects it anyway.
        if (! is_string($email)) {
            return true;
        }

        return mb_strtolower(trim($email)) !== mb_strtolower((string) $this->user()->email);
    }
}
