<?php

namespace App\Services\AiSearch;

use App\Support\BrowseFilters;
use Illuminate\Support\Collection;

/**
 * The "Searching for" chips: one per filter in the intent. `remove` tells the
 * page how to drop it from the intent before searching again. A chip is `miss`
 * when none of the results satisfies it, which is what turns it amber.
 */
class ChipBuilder
{
    /**
     * @param  Collection<int, array<string, mixed>>|null  $rows  null while only the intent is known
     * @return list<array{label: string, icon: string, state: string, remove: array{field: string, value: ?string}}>
     */
    public function build(array $intent, ?Collection $rows = null): array
    {
        $chips = [];
        $miss = function (string $facet) use ($rows): string {
            if ($rows === null || $rows->isEmpty()) {
                return 'ok';
            }

            return $rows->contains(fn ($r) => collect($r['hits'])->contains('facet', $facet)) ? 'ok' : 'miss';
        };

        if ($loc = $intent['location']) {
            $trip = $intent['max_minutes'] ?? null;
            $label = match ($loc['kind']) {
                'landmark' => is_array($trip) ? '≤ ' . $trip['minutes'] . ' min ' . ['walk' => 'walk', 'drive' => 'drive', 'jeep' => 'by jeep'][$trip['mode']] . ' to ' . $loc['name'] : 'Near ' . $loc['name'],
                'point' => 'Near you · ' . $loc['name'],
                'area' => 'In the map area',
                default => $loc['name'],
            };
            $chips[] = ['label' => $label, 'icon' => 'pin', 'state' => $miss('location'), 'remove' => ['field' => 'location', 'value' => null]];
        }
        foreach ($intent['anchors'] ?? [] as $anchor) {
            $chips[] = ['label' => 'Close to ' . $anchor['name'], 'icon' => 'pin', 'state' => $miss('anchor:' . $anchor['name']), 'remove' => ['field' => 'anchors', 'value' => $anchor['name']]];
        }
        foreach ($intent['avoid'] ?? [] as $i => $avoid) {
            if ($avoid['lat'] !== null) {
                $chips[] = ['label' => 'Away from ' . $avoid['name'], 'icon' => 'pin', 'state' => $miss('avoid:' . $i), 'remove' => ['field' => 'avoid', 'value' => $avoid['name']]];
            }
        }
        if ($host = $intent['host'] ?? null) {
            $chips[] = ['label' => ($host['kind'] === 'listing' ? 'Listing: ' : 'By ') . $host['name'], 'icon' => 'people', 'state' => $miss('host'), 'remove' => ['field' => 'host', 'value' => null]];
        }
        if ($intent['property_type']) {
            $chips[] = ['label' => $intent['property_type'], 'icon' => 'home', 'state' => $miss('type'), 'remove' => ['field' => 'property_type', 'value' => null]];
        }
        if ($intent['price_min'] !== null || $intent['price_max'] !== null) {
            $label = match (true) {
                $intent['price_min'] !== null && $intent['price_max'] !== null => '₱' . number_format($intent['price_min']) . ' to ₱' . number_format($intent['price_max']),
                $intent['price_max'] !== null => 'Up to ₱' . number_format($intent['price_max']),
                default => 'From ₱' . number_format($intent['price_min']),
            };
            $chips[] = ['label' => $label, 'icon' => 'peso', 'state' => $miss('price'), 'remove' => ['field' => 'price', 'value' => null]];
        }
        foreach ($intent['amenities'] as $name) {
            $chips[] = ['label' => $name, 'icon' => 'amenity', 'state' => $miss('amenity:' . $name), 'remove' => ['field' => 'amenities', 'value' => $name]];
        }
        foreach ($intent['rules'] as $key) {
            $chips[] = ['label' => BrowseFilters::RULES[$key][0], 'icon' => 'rule', 'state' => $miss('rule:' . $key), 'remove' => ['field' => 'rules', 'value' => $key]];
        }
        if ($intent['for']) {
            $chips[] = ['label' => 'For ' . strtolower($intent['for'] === 'Women' ? 'women' : 'men'), 'icon' => 'people', 'state' => $miss('for'), 'remove' => ['field' => 'for', 'value' => null]];
        }
        if (($intent['bedrooms_min'] ?? null) !== null) {
            $chips[] = ['label' => $intent['bedrooms_min'] . '+ bedrooms', 'icon' => 'home', 'state' => $miss('bedrooms'), 'remove' => ['field' => 'bedrooms_min', 'value' => null]];
        }
        if (($intent['deposit_max'] ?? null) !== null) {
            $chips[] = ['label' => $intent['deposit_max'] === 0 ? 'No deposit' : 'Deposit up to ₱' . number_format($intent['deposit_max']), 'icon' => 'peso', 'state' => $miss('deposit'), 'remove' => ['field' => 'deposit_max', 'value' => null]];
        }
        if (($intent['occupants'] ?? null) !== null) {
            $chips[] = ['label' => 'Good for ' . $intent['occupants'], 'icon' => 'people', 'state' => $miss('occupants'), 'remove' => ['field' => 'occupants', 'value' => null]];
        }
        if ($intent['living']) {
            $chips[] = ['label' => $intent['living'] . ' living', 'icon' => 'people', 'state' => $miss('living'), 'remove' => ['field' => 'living', 'value' => null]];
        }
        if ($intent['furnishing']) {
            $chips[] = ['label' => $intent['furnishing'], 'icon' => 'home', 'state' => $miss('furnishing'), 'remove' => ['field' => 'furnishing', 'value' => null]];
        }

        if (($intent['sort'] ?? null) !== null) {
            $chips[] = ['label' => self::SORT_LABELS[$intent['sort']], 'icon' => 'amenity', 'state' => 'ok', 'remove' => ['field' => 'sort', 'value' => null]];
        }

        return $chips;
    }

    private const SORT_LABELS = [
        'price_asc' => 'Cheapest first',
        'price_desc' => 'Most expensive first',
        'rating' => 'Best rated first',
        'newest' => 'Newest first',
        'nearest' => 'Nearest first',
    ];

    /** Apply a chip's `remove` to an intent. */
    public static function remove(array $intent, string $field, ?string $value): array
    {
        switch ($field) {
            case 'price':
                $intent['price_min'] = $intent['price_max'] = null;
                break;
            case 'anchors':
                $intent['anchors'] = array_values(array_filter($intent['anchors'] ?? [], fn ($a) => $a['name'] !== $value));
                break;
            case 'avoid':
                $intent['avoid'] = array_values(array_filter($intent['avoid'] ?? [], fn ($a) => $a['name'] !== $value));
                break;
            case 'amenities':
            case 'rules':
                $intent[$field] = array_values(array_diff($intent[$field], [$value]));
                break;
            default:
                if (array_key_exists($field, $intent)) {
                    $intent[$field] = null;
                }
        }

        return $intent;
    }
}
