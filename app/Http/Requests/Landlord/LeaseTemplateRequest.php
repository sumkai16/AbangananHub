<?php

namespace App\Http\Requests\Landlord;

use App\Models\LeaseTemplate;
use App\Support\LeaseBody;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** A lease template's wording and rules. Every rule is optional: blank falls back to the property, then the platform. */
class LeaseTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // Blank rules and empty sections are dropped here, so only real content is validated and stored.
        $this->merge(['house_rule_sections' => \App\Support\HouseRules::normalize((array) $this->input('house_rule_sections', [])) ?? []]);
    }

    public function rules(): array
    {
        $priced = in_array($this->input('late_fee_type'), ['fixed', 'percent'], true);

        return [
            'name' => ['required', 'string', 'max:100'],
            'welcome_message' => ['nullable', 'string', 'max:1500'],
            'assigned_properties' => ['array'],
            'assigned_properties.*' => ['integer', Rule::exists('properties', 'property_id')->where('landlord_id', $this->user()?->user_id)],
            'body' => ['nullable', 'string', 'max:40000'],
            'rent_grace_days' => ['nullable', 'integer', 'between:0,30'],
            'late_fee_type' => ['nullable', Rule::in(LeaseTemplate::LATE_FEE_TYPES)],
            'late_fee_value' => $priced
                ? ['required', 'numeric', 'min:0.01', $this->input('late_fee_type') === 'percent' ? 'max:100' : 'max:10000']
                : ['nullable'],
            'notice_days' => ['nullable', 'integer', 'between:1,180'],
            'required_documents' => ['array'],
            'required_documents.*' => [Rule::in(array_keys(\App\Support\RequiredDocuments::TYPES))],
            'custom_documents' => ['array', 'max:'.\App\Support\RequiredDocuments::MAX_CUSTOM],
            'custom_documents.*' => ['nullable', 'string', 'max:100'],
            'house_rule_sections' => ['array', 'max:'.\App\Support\HouseRules::MAX_SECTIONS],
            'house_rule_sections.*.title' => ['required', 'string', 'max:80'],
            'house_rule_sections.*.hidden' => ['nullable', 'boolean'],
            'house_rule_sections.*.rules' => ['required', 'array', 'max:'.\App\Support\HouseRules::MAX_RULES],
            'house_rule_sections.*.rules.*' => ['string', 'max:200'],
        ];
    }

    public function messages(): array
    {
        return ['late_fee_value.required' => 'Enter the late fee amount, or choose "Use property rule" or "No late fee".'];
    }

    /** The validated values as the model stores them. */
    public function templateAttributes(): array
    {
        $data = $this->validated();
        $priced = in_array($data['late_fee_type'] ?? null, ['fixed', 'percent'], true);

        return [
            'name' => $data['name'],
            'welcome_message' => filled($data['welcome_message'] ?? null) ? trim($data['welcome_message']) : null,
            'body' => LeaseBody::clean($data['body'] ?? null) ?: null,
            'rent_grace_days' => $data['rent_grace_days'] ?? null,
            'late_fee_type' => $data['late_fee_type'] ?? null,
            'late_fee_value' => $priced ? $data['late_fee_value'] : null,
            'notice_days' => $data['notice_days'] ?? null,
            'house_rule_sections' => $data['house_rule_sections'] ?: null,
            'required_documents' => \App\Support\RequiredDocuments::normalize($data['required_documents'] ?? [], $data['custom_documents'] ?? []),
        ];
    }
}
