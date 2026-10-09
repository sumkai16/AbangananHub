<?php

namespace App\Services\AiSearch;

use App\Models\Property;

/**
 * No-AI reading of a search sentence: finds place names, a property type word,
 * a budget and a few common needs by plain matching. Crude on purpose: it only
 * has to keep search useful when the AI is down, and its output still goes
 * through IntentValidator like the AI's.
 */
class KeywordFallback
{
    private const TYPES = [
        'Boarding House' => ['boarding house', 'boardinghouse', 'boarding', 'dorm', 'dormitory', 'room for rent', 'kwarto', 'kuwarto', 'kwarto'],
        'Bedspace' => ['bedspace', 'bed space', 'bed spacer', 'bedspacer'],
        'Condominium' => ['condominium', 'condo'],
        'Apartment' => ['apartment', 'apartelle', 'apt'],
        'House' => ['house', 'bahay', 'balay'],
    ];

    private const AMENITIES = [
        'Wi-Fi' => ['wifi', 'wi fi', 'internet'],
        'Air Conditioning' => ['aircon', 'air con', 'air conditioning', 'airconditioned', 'ac'],
        'Parking Space' => ['parking', 'parking space', 'garage'],
        'Private Bathroom' => ['private bathroom', 'private cr', 'own cr', 'private toilet'],
        'Washing Machine' => ['washing machine', 'laundry machine'],
        'Bed Included' => ['bed included', 'with bed'],
    ];

    private const RULES = [
        'pets' => ['pet', 'pets', 'cat', 'dog', 'pusa', 'iring', 'aso', 'iro'],
        'smoking' => ['smoking', 'smoke', 'yosi', 'sigarilyo'],
    ];

    /** @return array<string, mixed> raw intent (not yet validated) */
    public function parse(string $query): array
    {
        $text = Landmarks::normalize($query);
        $padded = ' ' . $text . ' ';

        $intent = ['language' => 'en', 'amenities' => [], 'rules' => [], 'unmapped' => []];

        // Place: landmark first (most specific), then barangay, then city.
        if ($landmark = Landmarks::find($text)) {
            $intent['location'] = ['kind' => 'landmark', 'value' => $landmark['key']];
        } else {
            $intent['location'] = $this->place($padded);
        }

        foreach (self::TYPES as $type => $words) {
            if ($this->hasAny($padded, $words)) {
                $intent['property_type'] = $type;
                break;
            }
        }

        foreach (self::AMENITIES as $amenity => $words) {
            if ($this->hasAny($padded, $words)) {
                $intent['amenities'][] = $amenity;
            }
        }
        foreach (self::RULES as $key => $words) {
            if ($this->hasAny($padded, $words)) {
                $intent['rules'][] = $key;
            }
        }

        if ($this->hasAny($padded, ['women', 'woman', 'girls', 'female', 'babae', 'ladies'])) {
            $intent['for'] = 'Women';
        } elseif ($this->hasAny($padded, ['men only', 'boys', 'male', 'lalaki', 'guys'])) {
            $intent['for'] = 'Men';
        }

        $price = $this->price($query);
        if ($price !== null) {
            $above = preg_match('/\b(above|over|at least|starting|mas? taas|labaw)\b/i', $query);
            $intent[$above ? 'price_min' : 'price_max'] = $price;
        }

        return $intent;
    }

    /** @return ?array{kind: string, value: string} */
    private function place(string $padded): ?array
    {
        $best = null;
        $bestLength = 0;
        foreach (Property::searchLocations() as $area) {
            $label = Landmarks::normalize($area['label']);
            $bare = preg_replace('/\scity$/', '', $label);
            foreach (array_unique([$label, $bare]) as $needle) {
                if ($needle !== '' && strlen($needle) > $bestLength && str_contains($padded, ' ' . $needle . ' ')) {
                    $best = ['kind' => $area['type'], 'value' => $area['label']];
                    $bestLength = strlen($needle);
                }
            }
        }

        return $best;
    }

    private function hasAny(string $padded, array $words): bool
    {
        foreach ($words as $word) {
            if (str_contains($padded, ' ' . $word . ' ')) {
                return true;
            }
        }

        return false;
    }

    /** "5k", "₱5,000", "5000", "4.5k" -> pesos. */
    private function price(string $query): ?int
    {
        if (preg_match('/(\d+(?:\.\d+)?)\s*k\b/i', $query, $m)) {
            return (int) round((float) $m[1] * 1000);
        }
        if (preg_match('/(?:₱|php|p)\s*(\d{1,3}(?:,\d{3})+|\d{3,6})/i', $query, $m) || preg_match('/\b(\d{1,3}(?:,\d{3})+|\d{4,6})\b/', $query, $m)) {
            return (int) str_replace(',', '', $m[1]);
        }

        return null;
    }
}
