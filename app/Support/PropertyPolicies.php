<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The three property-level "who is this for and what are the rules" answers,
 * in one place so the wizard, the edit page, the API and every display read
 * the same options.
 *
 * `house_rules` is stored as a flat JSON list of strings: the preset labels the
 * landlord ticked, then each custom line they typed. Presets are the labels in
 * HOUSE_RULES exactly; anything else in the list is a custom rule.
 */
class PropertyPolicies
{
    /** value => hint shown under the option */
    public const LIVING_ARRANGEMENTS = [
        'Private' => 'Each tenant has their own room or space',
        'Shared'  => 'Tenants share a room or bedspace',
        'Mixed'   => 'A mix of private and shared units',
    ];

    public const OCCUPANCY_PREFERENCES = [
        'No Preference' => 'Open to anyone',
        'Men Only'      => 'Men only',
        'Women Only'    => 'Women only',
    ];

    public const HOUSE_RULES = [
        'No Smoking',
        'No Pets',
        'No Parties / Events',
        'Quiet Hours',
        'Curfew',
        'No Overnight Guests',
        'Visitors Allowed',
    ];

    public const MAX_CUSTOM_RULES = 10;
    public const MAX_CUSTOM_RULE_LENGTH = 120;

    /**
     * Validation rules, merged into every form that edits a property.
     * Living arrangement and occupancy preference are required — a listing has
     * to say who it is for. House rules are a pick-any list, so none is valid.
     */
    public static function rules(): array
    {
        return [
            'living_arrangement'   => ['required', Rule::in(array_keys(self::LIVING_ARRANGEMENTS))],
            'occupancy_preference' => ['required', Rule::in(array_keys(self::OCCUPANCY_PREFERENCES))],
            'house_rules'          => ['nullable', 'array'],
            'house_rules.*'        => ['string', Rule::in(self::HOUSE_RULES)],
            'custom_house_rules'   => ['nullable', 'string', 'max:' . (self::MAX_CUSTOM_RULES * (self::MAX_CUSTOM_RULE_LENGTH + 1))],
        ];
    }

    /**
     * The attributes to write onto a Property, from a validated request.
     *
     * @return array{living_arrangement: string, occupancy_preference: string, house_rules: array<int, string>}
     */
    public static function fromRequest(Request $request): array
    {
        return [
            'living_arrangement'   => $request->input('living_arrangement'),
            'occupancy_preference' => $request->input('occupancy_preference'),
            'house_rules'          => self::compileRules(
                (array) $request->input('house_rules', []),
                $request->input('custom_house_rules')
            ),
        ];
    }

    /**
     * Ticked presets (in the canonical order) followed by the custom lines.
     *
     * @param  array<int, string>  $presets
     * @return array<int, string>
     */
    public static function compileRules(array $presets, ?string $customText): array
    {
        $chosen = array_values(array_filter(self::HOUSE_RULES, fn ($rule) => in_array($rule, $presets, true)));

        $custom = collect(preg_split('/\R/', (string) $customText) ?: [])
            ->map(fn ($line) => trim(preg_replace('/\s+/', ' ', $line)))
            ->filter()
            ->map(fn ($line) => mb_substr($line, 0, self::MAX_CUSTOM_RULE_LENGTH))
            // A custom line that repeats a preset (or another line) adds nothing.
            ->reject(fn ($line) => in_array(mb_strtolower($line), array_map('mb_strtolower', $chosen), true))
            ->unique(fn ($line) => mb_strtolower($line))
            ->take(self::MAX_CUSTOM_RULES)
            ->values()
            ->all();

        return array_merge($chosen, $custom);
    }

    /**
     * The inverse, for pre-filling the form: which presets are ticked and what
     * text belongs in the custom box.
     *
     * @param  array<int, string>|null  $rules
     * @return array{presets: array<int, string>, custom: string}
     */
    public static function splitRules(?array $rules): array
    {
        $rules = array_values(array_filter((array) $rules, 'is_string'));

        return [
            'presets' => array_values(array_intersect($rules, self::HOUSE_RULES)),
            'custom'  => implode("\n", array_values(array_diff($rules, self::HOUSE_RULES))),
        ];
    }

    /** Form pre-fill values for a saved property (or empty defaults). */
    public static function formValues(?object $property): array
    {
        $split = self::splitRules($property?->house_rules);

        return [
            'living_arrangement'   => $property?->living_arrangement,
            'occupancy_preference' => $property?->occupancy_preference ?? 'No Preference',
            'house_rules'          => $split['presets'],
            'custom_house_rules'   => $split['custom'],
        ];
    }
}
