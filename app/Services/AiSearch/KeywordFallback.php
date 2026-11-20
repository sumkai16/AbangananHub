<?php

namespace App\Services\AiSearch;

use App\Models\Property;

/**
 * No-AI reading of a search sentence: finds place names, a property type word,
 * a budget and a few common needs by plain matching. Crude on purpose: it only
 * has to keep search useful when the AI is down, and its output still goes
 * through IntentValidator like the AI's.
 *
 * Reads English, Tagalog and Bisaya words and the mixes of them ("lima ka libo", "kinse mil", "walay curfew",
 * "pwede ang iro"), because that is how people type. Checked against tests/fixtures/ai-search-cases.json.
 */
class KeywordFallback
{
    /** Order matters: the first type with a word in the sentence wins. A bare "kwarto" is the weakest word of all (see parse()). */
    private const TYPES = [
        'Boarding House' => ['boarding house', 'boardinghouse', 'boarding', 'room for rent'],
        'Bedspace' => ['bedspace', 'bed space', 'bed spacer', 'bedspacer', 'bedspase', 'bedspaces'],
        'Dormitory' => ['dormitory', 'dorm', 'dorms', 'dormitories'],
        'Apartment' => ['apartment', 'apartments', 'apartelle', 'apt', 'studio', 'apartmnt', 'apartement', 'aparment'],
        'House' => ['house', 'bahay', 'balay'],
    ];

    /** "room" in Tagalog and Bisaya. Only used when no other type word is in the sentence ("bahay na may 2 kwarto" is a house). */
    private const ROOM_WORDS = ['kwarto', 'kuwarto', 'kwarto'];

    private const AMENITIES = [
        'Wi-Fi' => ['wifi', 'wi fi', 'internet', 'wiffi', 'wifii'],
        'Air Conditioning' => ['aircon', 'air con', 'air conditioning', 'airconditioned', 'ac'],
        'Parking Space' => ['parking', 'parking space', 'garage', 'paradahan', 'garahe', 'parkin', 'parkingan'],
        'Motorcycle Parking' => ['motorcycle parking', 'motor parking', 'motorbike parking'],
        'Private Bathroom' => ['private bathroom', 'private cr', 'own cr', 'private toilet'],
        'Washing Machine' => ['washing machine', 'laundry machine'],
        'Bed Included' => ['bed included', 'with bed', 'may bed', 'naay bed', 'bed ug', 'bed and'],
        'Wardrobe / Cabinet' => ['wardrobe', 'cabinet', 'closet', 'aparador'],
        'Study Table' => ['study table', 'study desk'],
        'Laundry Area' => ['laundry area', 'laundry', 'labahan'],
        '24/7 Security' => ['24 7 security', 'security guard', 'guard', '24 7'],
        'Rooftop Access' => ['rooftop', 'roof deck'],
        'Balcony' => ['balcony', 'balkon'],
        'Elevator' => ['elevator', 'lift'],
        'Gated Entrance' => ['gated', 'gate'],
        'Backup Generator' => ['generator', 'backup power'],
        'Microwave' => ['microwave'],
        'Water Dispenser' => ['water dispenser'],
        'Shared Kitchen' => ['shared kitchen'],
        'Shared Bathroom' => ['shared bathroom', 'shared cr'],
        'CCTV' => ['cctv'],
        'Hot Shower' => ['hot shower', 'water heater', 'heater'],
        'Private Kitchen' => ['private kitchen', 'own kitchen'],
        'Electric Fan' => ['electric fan'],
        'Refrigerator' => ['refrigerator', 'fridge'],
    ];

    private const RULES = [
        'pets' => ['pet', 'pets', 'cat', 'cats', 'dog', 'dogs', 'pusa', 'iring', 'aso', 'iro', 'puppy', 'kitten'],
        'smoking' => ['smoking', 'smoke', 'yosi', 'sigarilyo'],
        'overnight' => ['bisita', 'visitors', 'visitor', 'overnight', 'guests'],
        'curfew' => ['no curfew', 'walang curfew', 'walay curfew', 'without curfew', 'curfew free', 'wala curfew'],
    ];

    /** The superlatives that sort. Checked in this order. */
    private const SORTS = [
        'price_asc' => ['cheapest', 'pinakamura', 'pinakabarato', 'lowest price', 'lowest priced'],
        'price_desc' => ['most expensive', 'pinakamahal', 'highest price', 'highest priced'],
        'rating' => ['best rated', 'highest rated', 'top rated', 'best reviewed', 'pinakamaayo ang review', 'pinakamataas ang rating'],
        'newest' => ['newest', 'latest', 'pinakabago', 'pinakabag o', 'recently listed', 'new listings', 'new listing', 'bag o nga listings', 'bag o nga listing', 'bagong listings', 'bagong listing'],
        'nearest' => ['nearest', 'closest', 'pinakamalapit', 'pinakaduol'],
    ];

    /** A rule word right after one of these is a "do not want" ("no pets", "bawal ang pets"), not "must allow". */
    private const NEGATIONS = ['no', 'bawal', 'walang', 'walay', 'wala', 'without', 'dili', 'hindi', 'ayaw'];

    /** Number words as people type prices: "limang libo", "lima ka libo", "kinse mil", "singko mil". */
    private const NUMBER_WORDS = [
        'uno' => 1, 'isa' => 1, 'usa' => 1,
        'dos' => 2, 'dalawa' => 2, 'dalawang' => 2, 'duha' => 2,
        'tres' => 3, 'tatlo' => 3, 'tatlong' => 3, 'tulo' => 3,
        'kwatro' => 4, 'apat' => 4, 'upat' => 4,
        'singko' => 5, 'lima' => 5, 'limang' => 5,
        'sais' => 6, 'anim' => 6, 'unom' => 6,
        'siyete' => 7, 'pito' => 7, 'pitong' => 7,
        'otso' => 8, 'walo' => 8, 'walong' => 8,
        'nuwebe' => 9, 'siyam' => 9, 'siyam na' => 9,
        'diyes' => 10, 'sampu' => 10, 'sampung' => 10, 'napulo' => 10, 'napulo ka' => 10,
        'onse' => 11, 'dose' => 12, 'trese' => 13, 'katorse' => 14,
        'kinse' => 15, 'labinlima' => 15, 'bente' => 20, 'baynte' => 20, 'trenta' => 30, 'kwarenta' => 40, 'singkwenta' => 50,
    ];

    /** What stands between two amounts in "3k to 4.5k", "3k-5k", "tulo ka libo ngadto sa lima ka libo". */
    private const RANGE_JOINERS = '/^\s*(?:to|-|–|—|and|hanggang|hangtod|hangtud|ngadto|until)(?:\s+(?:sa|ang|ng))?\s*$/iu';

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

        // "far from Colon": the place is something to stay away from, never the place searched near.
        if (preg_match('/\b(?:far from|away from|not near|layo sa|layo gikan sa|malayo sa|dili duol sa|dili malapit sa)\s+(.+?)(?=\s*(?:,|\.|$|\b(?:with|under|below|above|at|and|na|nga|max|budget|may|naay|pwede|for)\b|\d))/iu', $query, $m)) {
            $avoided = trim($m[1]);
            if ($avoided !== '') {
                $intent['avoid'] = [['name' => $avoided, 'radius_km' => null]];
                $same = Landmarks::find($avoided);
                if ($same && ($intent['location']['value'] ?? null) === $same['key']) {
                    $intent['location'] = null;
                }
            }
        }

        // "10 minutes walk to IT Park", "walking distance to ...": a travel time around the place.
        if (preg_match('/\b(\d{1,2})\s*(?:min(?:ute)?s?)\b/i', $query, $m)) {
            $mode = preg_match('/\b(drive|driving|car|biyahe|kotse)\b/i', $query) ? 'drive' : (preg_match('/\bjeep/i', $query) ? 'jeep' : 'walk');
            $intent['max_minutes'] = ['minutes' => (int) $m[1], 'mode' => $mode];
        } elseif (preg_match('/\b(walking distance|lakaw lang|layo lakaw|lalakarin)\b/i', $query)) {
            $intent['max_minutes'] = ['minutes' => 10, 'mode' => 'walk'];
        }

        foreach (self::TYPES as $type => $words) {
            if ($this->hasAny($padded, $words)) {
                $intent['property_type'] = $type;
                break;
            }
        }
        if (! isset($intent['property_type']) && $this->hasAny($padded, self::ROOM_WORDS)) {
            $intent['property_type'] = 'Boarding House';
        }

        // "motorcycle parking" is its own amenity: it must not also count as a car Parking Space.
        $carPadded = str_replace([' motorcycle parking ', ' motor parking ', ' motorbike parking '], ' ', $padded);
        foreach (self::AMENITIES as $amenity => $words) {
            if ($this->hasAny($amenity === 'Parking Space' ? $carPadded : $padded, $words)) {
                $intent['amenities'][] = $amenity;
            }
        }
        foreach (self::RULES as $key => $words) {
            // Pets, smoking and guests are "must allow": "no pets" asks for the opposite, so it adds nothing.
            // A curfew phrase already says "no curfew", so it needs no guard.
            $found = $key === 'curfew' ? $this->hasAny($padded, $words) : $this->hasAnyUnlessNegated($padded, $words);
            if ($found) {
                $intent['rules'][] = $key;
            }
        }

        if ($this->hasAny($padded, ['unfurnished'])) {
            $intent['furnishing'] = 'Unfurnished';
        } elseif ($this->hasAny($padded, ['semi furnished', 'semifurnished'])) {
            $intent['furnishing'] = 'Semi-furnished';
        } elseif ($this->hasAny($padded, ['furnished', 'fully furnished'])) {
            $intent['furnishing'] = 'Furnished';
        }

        if ($this->hasAny($padded, ['women', 'woman', 'girls', 'girl', 'female', 'babae', 'babaye', 'ladies', 'pambabae', 'pang babae', 'for women', 'women only'])) {
            $intent['for'] = 'Women';
        } elseif ($this->hasAny($padded, ['men only', 'boys', 'male', 'lalaki', 'guys', 'pang lalaki', 'panlalaki', 'for men'])) {
            $intent['for'] = 'Men';
        }

        // Order. "cheap" alone is a budget hint, not a sort: only the superlative sorts.
        foreach (self::SORTS as $sort => $words) {
            if ($this->hasAny($padded, $words)) {
                $intent['sort'] = $sort;
                break;
            }
        }

        // Deposit: "no deposit" is 0; "deposit up to 3k" is a limit and must not be read as the rent budget.
        $priceQuery = $query;
        if ($this->hasAny($padded, ['no deposit', 'walang deposit', 'walay deposit', 'without deposit', 'zero deposit'])) {
            $intent['deposit_max'] = 0;
        } elseif (preg_match('/deposit\s+(?:of\s+)?(?:up to|under|below|max|ubos sa|hanggang)?\s*(\d+(?:\.\d+)?)\s*k\b/i', $query, $m)) {
            $intent['deposit_max'] = (int) round((float) $m[1] * 1000);
            $priceQuery = str_replace($m[0], ' ', $query);
        }

        // Bedrooms only for places that have them: with a boarding house, "kwarto" is the kind of place.
        $type = $intent['property_type'] ?? null;
        if (preg_match('/\b(\d)\s*(?:ka\s+)?(bedrooms?|br|bdr|kwarto|kuwarto)\b/i', $query, $m)
            && (in_array($type, ['Apartment', 'House'], true) || preg_match('/^(bedrooms?|br|bdr)$/i', $m[2]))) {
            $intent['bedrooms_min'] = (int) $m[1];
        } elseif (in_array($type, ['Apartment', 'House'], true)
            && preg_match('/\b(isang|dalawang|dalawa|duha|tatlong|tatlo|tulo|apat|upat)\s+(?:ka\s+)?(?:kwarto|kuwarto|bedrooms?)\b/i', $query, $m)) {
            $intent['bedrooms_min'] = ['isang' => 1, 'dalawang' => 2, 'dalawa' => 2, 'duha' => 2, 'tatlong' => 3, 'tatlo' => 3, 'tulo' => 3, 'apat' => 4, 'upat' => 4][mb_strtolower($m[1])];
        }

        // Group size: "good for 2", "family of 4", "for 3 persons"; a couple is 2.
        if (preg_match('/\b(?:good for|family of|group of|room for|for)\s+(\d{1,2})(?![\d,.]*\s*k\b)(?:\s+(?:persons?|people|pax|tao|ka tawo))?\b/i', $query, $m) && (int) $m[1] >= 1 && (int) $m[1] <= 20) {
            $intent['occupants'] = (int) $m[1];
        } elseif ($this->hasAny($padded, ['couple', 'magpartner', 'mag partner', 'magkapartner', 'kami duha'])) {
            $intent['occupants'] = 2;
        }

        $amounts = $this->amounts($priceQuery);
        if (count($amounts) >= 2 && preg_match(self::RANGE_JOINERS, substr($priceQuery, $amounts[0]['end'], $amounts[1]['start'] - $amounts[0]['end']))) {
            $intent['price_min'] = min($amounts[0]['value'], $amounts[1]['value']);
            $intent['price_max'] = max($amounts[0]['value'], $amounts[1]['value']);
        } elseif ($amounts !== []) {
            // "above 8k", "at least 8k", and "12k up" / "12k and above" (the word comes after the amount).
            $above = preg_match('/\b(above|over|at least|starting|more than|mas? taas|labaw|pataas)\b/i', $priceQuery)
                || preg_match('/(?:\d|k|libo|mil)\s*(?:\+|&|and\s+)?\s*(?:up|above|pataas)\b/i', $priceQuery);
            $intent[$above ? 'price_min' : 'price_max'] = $amounts[0]['value'];
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

    /** Like hasAny, but a word straight after "no", "bawal", "walang" ... does not count. */
    private function hasAnyUnlessNegated(string $padded, array $words): bool
    {
        $negation = implode('|', self::NEGATIONS);

        foreach ($words as $word) {
            // "no pets", "bawal ang pets", "walay iro": the negation, an optional "ang"/"ng"/"sa", then the word.
            $pattern = '/ ' . preg_quote($word, '/') . ' /';
            $negated = '/ (?:' . $negation . ') (?:ang |ng |sa |ug |ra |koy |ko |akong |kong )?' . preg_quote($word, '/') . ' /';
            if (preg_match($pattern, $padded) && ! preg_match($negated, $padded)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Every amount in the sentence, in order: "5k", "4.5k", "₱5,000", "5000", "limang libo", "kinse mil".
     * Small plain numbers ("2 bedrooms") are not amounts.
     *
     * @return list<array{value: int, start: int, end: int}>
     */
    private function amounts(string $query): array
    {
        $words = implode('|', array_map(fn ($w) => preg_quote($w, '/'), array_keys(self::NUMBER_WORDS)));
        $pattern = '/(?<![\p{L}\p{N}])(?:'
            . '(?<word>' . $words . ')(?:\s+ka)?\s+(?:libo|mil|thousand)(?![\p{L}])'     // lima ka libo, kinse mil
            . '|(?<k>\d+(?:\.\d+)?)\s*k(?![\p{L}\p{N}])'                                    // 5k, 4.5k
            . '|(?:₱|php\s*|p\s*)(?<peso>\d{1,3}(?:,\d{3})+|\d{3,6})(?!\d)'                  // ₱5,000, p3500
            . '|(?<plain>\d{1,3}(?:,\d{3})+|\d{4,6})(?!\d)'                                  // 5000, 5,000
            . ')/iu';

        if (! preg_match_all($pattern, $query, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            return [];
        }

        $found = [];
        foreach ($matches as $m) {
            $value = match (true) {
                ($m['word'][0] ?? '') !== '' => self::NUMBER_WORDS[mb_strtolower($m['word'][0])] * 1000,
                ($m['k'][0] ?? '') !== '' => (int) round((float) $m['k'][0] * 1000),
                ($m['peso'][0] ?? '') !== '' => (int) str_replace(',', '', $m['peso'][0]),
                default => (int) str_replace(',', '', $m['plain'][0] ?? '0'),
            };
            $found[] = ['value' => $value, 'start' => $m[0][1], 'end' => $m[0][1] + strlen($m[0][0])];
        }

        return $found;
    }
}
