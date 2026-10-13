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
            $label = $loc['kind'] === 'landmark' ? 'Near ' . $loc['name'] : $loc['name'];
            $chips[] = ['label' => $label, 'icon' => 'pin', 'state' => $miss('location'), 'remove' => ['field' => 'location', 'value' => null]];
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
        if ($intent['living']) {
            $chips[] = ['label' => $intent['living'] . ' living', 'icon' => 'people', 'state' => $miss('living'), 'remove' => ['field' => 'living', 'value' => null]];
        }
        if ($intent['furnishing']) {
            $chips[] = ['label' => $intent['furnishing'], 'icon' => 'home', 'state' => $miss('furnishing'), 'remove' => ['field' => 'furnishing', 'value' => null]];
        }

        return $chips;
    }

    /** Apply a chip's `remove` to an intent. */
    public static function remove(array $intent, string $field, ?string $value): array
    {
        switch ($field) {
            case 'price':
                $intent['price_min'] = $intent['price_max'] = null;
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
