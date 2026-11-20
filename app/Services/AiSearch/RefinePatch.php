<?php

namespace App\Services\AiSearch;

/**
 * Follow-ups ("cheaper", "closer to school", "may aircon") change the search the tenant already has instead of
 * starting over. Everything here is plain PHP and deterministic: the model (or a quick chip) says WHAT to change, the
 * numbers are worked out here, and the result goes through IntentValidator like any other intent.
 *
 * A patch: { set: {field: value}, add: {amenities: [], rules: []}, remove: [field], ops: [cheaper|pricier|closer|wider] }.
 */
class RefinePatch
{
    public const OPS = ['cheaper', 'pricier', 'closer', 'wider'];

    /** Fields a patch may set or remove. Anything else is ignored; the validator checks the values. */
    private const FIELDS = [
        'location', 'property_type', 'price_min', 'price_max', 'living', 'for', 'furnishing', 'occupants',
        'sort', 'bedrooms_min', 'deposit_max', 'max_minutes', 'avoid',
    ];

    /**
     * @param  array<string, mixed>  $previous  a validated intent
     * @param  array<string, mixed>  $patch
     * @param  array<string, mixed>  $context  what the page knows: ['cheapest_fee' => lowest rent among the results shown]
     * @return array<string, mixed> a raw intent, to be validated
     */
    public static function apply(array $previous, array $patch, array $context = []): array
    {
        $intent = $previous;

        foreach ((array) ($patch['remove'] ?? []) as $field) {
            if ($field === 'avoid') {
                $intent['avoid'] = [];
            } elseif (in_array($field, self::FIELDS, true)) {
                $intent = ChipBuilder::remove($intent, $field === 'price_min' || $field === 'price_max' ? 'price' : $field, null);
            }
        }

        foreach ((array) ($patch['set'] ?? []) as $field => $value) {
            if (in_array($field, self::FIELDS, true)) {
                $intent[$field] = $value;
            }
        }

        foreach (['amenities', 'rules'] as $list) {
            $intent[$list] = array_values(array_unique(array_merge((array) ($intent[$list] ?? []), array_filter((array) ($patch['add'][$list] ?? []), 'is_string'))));
        }

        foreach (array_values(array_intersect((array) ($patch['ops'] ?? []), self::OPS)) as $op) {
            $intent = self::op($intent, $op, $context);
        }

        return $intent;
    }

    private const WORDS = [
        'cheaper' => ['cheaper', 'mas mura', 'barato pa', 'mas barato', 'ubos pa', 'mas ubos', 'lower budget', 'less expensive'],
        'pricier' => ['pricier', 'more expensive', 'higher budget', 'mas mahal', 'mas taas'],
        'closer' => ['closer', 'nearer', 'mas malapit', 'mas duol'],
        'wider' => ['wider', 'farther', 'further', 'mas layo', 'mas lapad'],
    ];

    /** Words that mean "drop that filter" (anywhere, any type, no budget): only then may a follow-up end with one fewer. */
    public static function releasesFilters(string $message): bool
    {
        $padded = ' ' . Landmarks::normalize($message) . ' ';

        foreach (['anywhere', 'kahit saan', 'kahit asa', 'kahit diin', 'any type', 'any kind', 'kahit unsa', 'kahit ano', 'any budget', 'no budget', 'walang limit', 'walay limit', 'remove the', 'tanggalin', 'tangtanga', 'without the', 'forget the'] as $word) {
            if (str_contains($padded, ' ' . $word . ' ')) {
                return true;
            }
        }

        return false;
    }

    /**
     * The no-AI follow-up: what the keyword reader finds in the message is added to the current search, the
     * relative words ("cheaper", "mas duol") become ops. Crude on purpose, like KeywordFallback itself.
     *
     * @param  array<string, mixed>  $previous  validated
     * @param  array<string, mixed>  $parsed  validated reading of the message alone
     * @return array{intent: array<string, mixed>, ops: list<string>}
     */
    public static function mergeKeywords(array $previous, array $parsed, string $message): array
    {
        $intent = $previous;

        foreach (['location', 'property_type', 'price_min', 'price_max', 'living', 'for', 'furnishing', 'occupants', 'sort', 'bedrooms_min', 'deposit_max', 'max_minutes'] as $field) {
            if (($parsed[$field] ?? null) !== null) {
                $intent[$field] = $parsed[$field];
            }
        }
        foreach (['amenities', 'rules', 'avoid'] as $list) {
            $intent[$list] = array_values(array_merge((array) ($previous[$list] ?? []), array_filter((array) ($parsed[$list] ?? []), fn ($v) => ! in_array($v, (array) ($previous[$list] ?? []), true))));
        }

        $padded = ' ' . Landmarks::normalize($message) . ' ';
        $ops = [];
        foreach (self::WORDS as $op => $words) {
            foreach ($words as $word) {
                if (str_contains($padded, ' ' . $word . ' ')) {
                    $ops[] = $op;
                    break;
                }
            }
        }

        return ['intent' => $intent, 'ops' => $ops];
    }

    /** @param  array<string, mixed>  $intent */
    private static function op(array $intent, string $op, array $context): array
    {
        $cheapest = is_numeric($context['cheapest_fee'] ?? null) ? (float) $context['cheapest_fee'] : null;

        switch ($op) {
            case 'cheaper':
                // 15% under the budget they have; with none, 15% under the cheapest place they are looking at.
                $base = $intent['price_max'] ?? $cheapest;
                if ($base !== null) {
                    $intent['price_max'] = (int) (floor(($base * 0.85) / 100) * 100);
                }
                break;
            case 'pricier':
                $base = $intent['price_max'] ?? $cheapest;
                if ($base !== null) {
                    $intent['price_max'] = (int) (ceil(($base * 1.25) / 100) * 100);
                }
                break;
            case 'closer':
            case 'wider':
                // Only a place with a radius can get closer or wider: minutes become a plain radius first.
                if (isset($intent['location']['lat']) && ($intent['location']['kind'] ?? null) !== 'area') {
                    $now = Travel::effectiveRadiusKm($intent);
                    $intent['max_minutes'] = null;
                    $intent['radius_km'] = $op === 'closer' ? max(0.5, round($now / 2, 2)) : min(10.0, round($now * 2, 2));
                }
                break;
        }

        return $intent;
    }

    /**
     * The previous search as the provider sees it: names and values only. The tenant's own position and a drawn map
     * area are left out on purpose (they never leave the server); the note tells the model they are still applied.
     *
     * @param  array<string, mixed>  $intent  a validated intent
     * @return array<string, mixed>
     */
    public static function forPrompt(array $intent): array
    {
        $loc = $intent['location'] ?? null;
        $shown = match ($loc['kind'] ?? null) {
            'landmark' => isset($loc['source']) ? ['kind' => 'text', 'value' => $loc['name']] : ['kind' => 'landmark', 'value' => $loc['value']],
            'city', 'barangay', 'text' => ['kind' => $loc['kind'], 'value' => $loc['value']],
            default => null, // point, area, none
        };

        return array_filter([
            'location' => $shown,
            'property_type' => $intent['property_type'] ?? null,
            'price_min' => $intent['price_min'] ?? null,
            'price_max' => $intent['price_max'] ?? null,
            'amenities' => $intent['amenities'] ?? [],
            'rules' => $intent['rules'] ?? [],
            'living' => $intent['living'] ?? null,
            'for' => $intent['for'] ?? null,
            'furnishing' => $intent['furnishing'] ?? null,
            'occupants' => $intent['occupants'] ?? null,
            'sort' => $intent['sort'] ?? null,
            'bedrooms_min' => $intent['bedrooms_min'] ?? null,
            'deposit_max' => $intent['deposit_max'] ?? null,
            'max_minutes' => $intent['max_minutes'] ?? null,
            'avoid' => array_map(fn ($a) => ['place' => $a['name']], (array) ($intent['avoid'] ?? [])),
            'location_note' => in_array($loc['kind'] ?? null, ['point', 'area'], true)
                ? 'The tenant\'s own position or a map area is already applied. Return a location only if the follow-up names a different place.'
                : null,
        ], fn ($v) => $v !== null && $v !== []);
    }

    /**
     * Quick suggestions under the results, worked out from the search and what it found. No AI call: each one is a
     * ready-made patch the page sends back.
     *
     * @param  array<string, mixed>  $intent
     * @return list<array{label: string, patch: array<string, mixed>}>
     */
    public static function quick(array $intent, int $rowCount): array
    {
        if ($rowCount === 0) {
            return [];
        }

        $quick = [['label' => 'Cheaper', 'patch' => ['ops' => ['cheaper']]]];

        $loc = $intent['location'] ?? null;
        if (isset($loc['lat']) && ($loc['kind'] ?? null) !== 'area') {
            $quick[] = ['label' => 'Closer', 'patch' => ['ops' => ['closer']]];
        }

        foreach (['Air Conditioning' => 'Add aircon', 'Wi-Fi' => 'Add Wi-Fi', 'Parking Space' => 'Add parking'] as $amenity => $label) {
            if (count($quick) >= 4) {
                break;
            }
            if (! in_array($amenity, $intent['amenities'] ?? [], true)) {
                $quick[] = ['label' => $label, 'patch' => ['add' => ['amenities' => [$amenity]]]];
            }
        }

        if (count($quick) < 4 && ! in_array('pets', $intent['rules'] ?? [], true)) {
            $quick[] = ['label' => 'Pets OK', 'patch' => ['add' => ['rules' => ['pets']]]];
        }

        return array_slice($quick, 0, 4);
    }
}
