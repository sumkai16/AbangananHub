<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class SendMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('view', $this->route('conversation'));
    }

    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'max:2000'],
        ];
    }
}