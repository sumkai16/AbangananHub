<?php

namespace App\Http\Requests;

use App\Models\TenantConcern;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreTenantConcernRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('create', [TenantConcern::class, $this->route('conversation')]);
    }

    public function rules(): array
    {
        return [
            'category' => ['required', Rule::in(TenantConcern::CATEGORIES)],
            'description' => ['required', 'string', 'max:1000'],
            'photos' => ['nullable', 'array', 'max:'.TenantConcern::MAX_PHOTOS],
            'photos.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'category.required' => 'Pick what the concern is about.',
            'description.required' => 'Tell your landlord what is wrong.',
            'photos.max' => 'You can attach up to '.TenantConcern::MAX_PHOTOS.' photos.',
            'photos.*.image' => 'Photos must be JPG, PNG or WebP images.',
            'photos.*.max' => 'Each photo must be under 5 MB.',
        ];
    }
}
