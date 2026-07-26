<?php

namespace App\Http\Requests\Admin;

use App\Models\Setting;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Rules are derived from Setting::DEFINITIONS, so the whitelist lives in exactly
 * one place. Every key is nullable: a blank field clears the override and the key
 * falls back to its config/rentals.php default.
 */
class UpdateSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasRole('Admin');
    }

    /**
     * The comma-separated list fields arrive as one text input; split them before
     * validation so each entry can be validated as an integer on its own.
     */
    protected function prepareForValidation(): void
    {
        $settings = (array) $this->input('settings', []);

        foreach (Setting::DEFINITIONS as $key => $definition) {
            if ($definition['type'] !== 'integer_list' || ! array_key_exists($key, $settings)) {
                continue;
            }

            $parts = array_values(array_filter(
                array_map('trim', explode(',', (string) $settings[$key])),
                fn($part) => $part !== ''
            ));

            // Empty means "clear the override", which the nullable rules allow;
            // an empty array would trip `min:1` instead.
            $settings[$key] = $parts ?: null;
        }

        $this->merge(['settings' => $settings]);
    }

    public function rules(): array
    {
        $rules = ['settings' => ['array']];

        foreach (Setting::DEFINITIONS as $key => $definition) {
            $rules["settings.$key"] = array_merge(['nullable'], $definition['rule']);

            if (isset($definition['item_rule'])) {
                $rules["settings.$key.*"] = $definition['item_rule'];
            }
        }

        return $rules;
    }

    public function attributes(): array
    {
        $attributes = [];

        foreach (Setting::DEFINITIONS as $key => $definition) {
            $attributes["settings.$key"]   = strtolower($definition['label']);
            $attributes["settings.$key.*"] = strtolower($definition['label']) . ' entry';
        }

        return $attributes;
    }
}
