<?php

namespace App\Services\AiSearch;

use App\Support\BrowseFilters;

/**
 * JSON schemas for strict structured output (response_format json_schema). Built from Vocabulary, so the model can
 * only answer with values we know; IntentValidator still checks everything (a schema is a courtesy to the model,
 * never the safety net). Rules for strict schemas: every property listed in `required`, additionalProperties false,
 * no numeric bounds, "nullable" written as anyOf with null.
 */
class Schemas
{
    public const SORTS = ['price_asc', 'price_desc', 'rating', 'newest', 'nearest'];

    public const TRAVEL_MODES = ['walk', 'drive', 'jeep'];

    public const PLANS = ['none', 'multi_place', 'commute', 'compare'];

    /** @param  array<string, mixed>  $vocab  Vocabulary::forPrompt() */
    public static function interpret(array $vocab): array
    {
        $enum = fn (array $values) => ['type' => 'string', 'enum' => array_values($values)];
        $nullable = fn (array $type) => ['anyOf' => [$type, ['type' => 'null']]];
        $number = ['type' => 'number'];

        $properties = [
            'location' => $nullable(self::object([
                'kind' => $enum(['landmark', 'city', 'barangay', 'text']),
                'value' => ['type' => 'string'],
            ])),
            'property_type' => $nullable($enum($vocab['property_types'])),
            'price_min' => $nullable($number),
            'price_max' => $nullable($number),
            'amenities' => ['type' => 'array', 'items' => $enum($vocab['amenities'])],
            'rules' => ['type' => 'array', 'items' => $enum(array_keys(BrowseFilters::RULES))],
            'living' => $nullable($enum(self::keys($vocab['living']))),
            'for' => $nullable($enum($vocab['for'])),
            'furnishing' => $nullable($enum($vocab['furnishing'])),
            'occupants' => $nullable(['type' => 'integer']),
            'sort' => $nullable($enum(self::SORTS)),
            'bedrooms_min' => $nullable(['type' => 'integer']),
            'deposit_max' => $nullable($number),
            'max_minutes' => $nullable(self::object([
                'minutes' => ['type' => 'integer'],
                'mode' => $enum(self::TRAVEL_MODES),
            ])),
            'avoid' => [
                'type' => 'array',
                'items' => self::object([
                    'place' => ['type' => 'string'],
                    'radius_km' => $nullable($number),
                ]),
            ],
            'plan' => $enum(self::PLANS),
            'language' => $enum(['en', 'tl', 'ceb', 'mixed']),
            'unmapped' => ['type' => 'array', 'items' => ['type' => 'string']],
        ];

        return self::object($properties);
    }

    /** One line per listing, plus the summary in the tenant's language and in English. */
    public static function reasons(): array
    {
        return self::object([
            'reasons' => ['type' => 'array', 'items' => self::object(['id' => ['type' => 'integer'], 'text' => ['type' => 'string']])],
            'summary' => ['type' => 'string'],
            'summary_en' => ['type' => 'string'],
        ]);
    }

    /** The follow-up answer: the updated search in full, whether it is a refinement or a new search, plus relative ops. */
    public static function refine(array $vocab): array
    {
        return self::object([
            'mode' => ['type' => 'string', 'enum' => ['refine', 'new']],
            'ops' => ['type' => 'array', 'items' => ['type' => 'string', 'enum' => RefinePatch::OPS]],
            'intent' => self::interpret($vocab),
        ]);
    }

    /** @param  array<string, array<string, mixed>>  $properties */
    public static function object(array $properties): array
    {
        return [
            'type' => 'object',
            'properties' => $properties,
            'required' => array_keys($properties),
            'additionalProperties' => false,
        ];
    }

    /** "Owner = Description" style entries to their keys. */
    private static function keys(array $items): array
    {
        return array_map(fn ($i) => trim(explode(' = ', (string) $i)[0]), $items);
    }
}
