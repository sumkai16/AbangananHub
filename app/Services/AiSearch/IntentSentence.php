<?php

namespace App\Services\AiSearch;

/**
 * Writes a validated intent back as one plain English sentence ("Bedspace near Cebu IT Park, under ₱3,000, with
 * Air Conditioning"). Used by search-by-screenshot: the image is read into an intent once, then the page searches this
 * sentence like anything a tenant typed, so every existing path (chips, follow-ups, map) works with no new state.
 */
class IntentSentence
{
    /** @param  array<string, mixed>  $intent  validated */
    public static function from(array $intent): string
    {
        $parts = [];

        $what = $intent['property_type'] ?? 'Place';
        $loc = $intent['location'] ?? null;
        if ($loc) {
            $where = match ($loc['kind']) {
                'landmark' => 'near ' . $loc['name'],
                'point', 'area' => '',
                default => 'in ' . $loc['name'],
            };
            $what .= $where === '' ? '' : ' ' . $where;
        }
        $parts[] = $what;

        if ($intent['price_min'] !== null && $intent['price_max'] !== null) {
            $parts[] = '₱' . number_format($intent['price_min']) . ' to ₱' . number_format($intent['price_max']);
        } elseif ($intent['price_max'] !== null) {
            $parts[] = 'under ₱' . number_format($intent['price_max']);
        } elseif ($intent['price_min'] !== null) {
            $parts[] = 'above ₱' . number_format($intent['price_min']);
        }

        if ($intent['for'] ?? null) {
            $parts[] = $intent['for'] === 'Women' ? 'for women' : 'for men';
        }
        if ($intent['furnishing'] ?? null) {
            $parts[] = strtolower($intent['furnishing']);
        }
        if (($intent['bedrooms_min'] ?? null) !== null) {
            $parts[] = $intent['bedrooms_min'] . ' bedrooms';
        }
        if ($intent['amenities'] ?? []) {
            $parts[] = 'with ' . implode(', ', $intent['amenities']);
        }
        foreach ($intent['rules'] ?? [] as $rule) {
            $parts[] = strtolower(\App\Support\BrowseFilters::RULES[$rule][0]);
        }

        return mb_substr(implode(', ', $parts), 0, 300);
    }
}
