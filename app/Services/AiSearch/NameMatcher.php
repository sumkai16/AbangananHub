<?php

namespace App\Services\AiSearch;

use App\Models\Property;
use Illuminate\Support\Facades\Cache;

/**
 * Finds out whether a search sentence names a rental business or a listing ("property ni maria santos",
 * "Lozada Urban Living", "Mabolo Garden Apartment") and, when it names something we do not have, says so.
 *
 * Done here, against our own tables, never by the AI: a model does not query the database or state facts
 * (RULES.md > AI search), and the provider only ever sees the sentence.
 *
 * What is searchable: business names and listing titles of live listings with something bookable. Personal
 * names are not indexed on purpose. Someone typing "Wilfredo" must not get the list of what a named person
 * owns; "maria santos" finds Santos Rentals Cebu through the shared surname and the page says so.
 *
 * Matching:
 *  - business: every distinctive word of its name is in the sentence (the words left after dropping generic
 *    ones: "Santos Rentals Cebu" -> santos), or the whole name is. A lone short word needs a name marker
 *    ("ni", "kang", "owned by") before it counts, so "yap" alone does not.
 *  - listing: the whole title (or the part before its first comma) is in the sentence, and the title has a
 *    distinctive word. Partial title matches would turn "dormitory near IT Park" into one specific listing.
 *
 * Intent keys this writes: `host` ({kind, landlord_ids, property_ids, name, typed}) and `name_miss`.
 */
class NameMatcher
{
    private const CACHE_KEY = 'search.host_index';

    /** Words that describe a kind of place or a company form, not whose it is. Compared after normalize(). */
    private const GENERIC = [
        'rentals', 'rental', 'rent', 'rents', 'homes', 'home', 'properties', 'property', 'living', 'stays', 'stay',
        'boarding', 'house', 'houses', 'dorm', 'dorms', 'dormitory', 'dormitories', 'apartment', 'apartments',
        'apartelle', 'rooms', 'room', 'bedspace', 'bedspaces', 'residences', 'residence', 'residential', 'realty',
        'real', 'estate', 'inn', 'lodge', 'lodging', 'suites', 'suite', 'family', 'group', 'company', 'corp', 'inc',
        'enterprises', 'enterprise', 'services', 'management', 'urban', 'city', 'north', 'south', 'east', 'west',
        'western', 'eastern', 'northern', 'southern', 'central', 'cebu', 'the', 'and', 'of', 'at', 'in', 'by', 'for',
        'sa', 'ng', 'ni', 'ug', 'near', 'with', 'new', 'old', 'private', 'shared', 'affordable', 'female', 'male',
        'students', 'student', 'unit', 'units', 'studio', 'studios', 'place', 'places', 'building', 'tower', 'towers',
    ];

    /** Words that put a name after them. "by" alone is too common ("rooms by the sea"), so only the phrases. */
    private const MARKERS = ['ni', 'kang', 'owned by', 'listed by', 'posted by', 'managed by', 'named', 'called', 'property of', 'properties of', 'place of'];

    /** Words that end a name phrase: where the rest of the sentence starts. */
    private const BOUNDARY = [
        'sa', 'in', 'at', 'near', 'malapit', 'duol', 'under', 'below', 'above', 'over', 'for', 'with', 'na', 'nga', 'and',
        'or', 'around', 'within', 'budget', 'price', 'php', 'peso', 'pesos', 'around', 'except', 'pero', 'but',
    ];

    /** Common words that are not a name even when they follow a marker ("kang" + "mura"). */
    private const NOT_A_NAME = ['mura', 'barato', 'dako', 'gamay', 'nice', 'good', 'clean', 'cheap', 'naa', 'wala', 'lang', 'ra', 'pud', 'ako', 'ikaw', 'mo', 'ko', 'ka', 'siya', 'nila', 'namin', 'natin'];

    public function __construct(private KeywordFallback $keywords) {}

    /**
     * Reads the sentence and sets `host` / `name_miss` on a validated intent.
     *
     * @param  array<string, mixed>  $intent
     * @return array<string, mixed>
     */
    public function apply(string $query, array $intent): array
    {
        $intent['host'] = null;
        $intent['name_miss'] = null;

        if (trim($query) === '') {
            return $intent;
        }

        $read = $this->read($query);
        $intent['host'] = $read['host'];
        $intent['name_miss'] = $read['miss'];

        if ($read['host'] !== null && $read['phrases'] !== []) {
            $intent = $this->withoutNameFilters($query, $intent, $read['phrases']);
        }

        // The model often files "maria santos" under location as plain text, which then matches nothing.
        // When the name accounts for those words, the name wins and the location goes.
        $location = $intent['location'] ?? null;
        if (($read['host'] !== null || $read['miss'] !== null) && is_array($location) && ($location['kind'] ?? null) === 'text') {
            $words = array_filter(explode(' ', Landmarks::normalize((string) ($location['value'] ?? ''))));
            if ($words !== [] && array_diff($words, $read['covered']) === []) {
                $intent['location'] = null;
            }
        }

        return $intent;
    }

    /**
     * @return array{host: ?array<string, mixed>, miss: ?string, covered: list<string>, phrases: list<string>}
     */
    public function read(string $query): array
    {
        $text = Landmarks::normalize($query);
        $padded = ' ' . $text . ' ';
        $tokens = array_flip(array_filter(explode(' ', $text)));

        $typed = $this->typedName($text);
        $minLength = $typed !== null ? 3 : 4;
        $index = $this->index();

        $landlordIds = [];
        $names = [];
        $covered = $typed !== null ? explode(' ', $typed) : [];
        $phrases = [];

        foreach ($index['businesses'] as $business) {
            if ($this->matches($business, $padded, $tokens, $minLength, partial: true)) {
                $landlordIds[] = $business['landlord_id'];
                $names[] = $business['name'];
                $covered = array_merge($covered, $business['words'], explode(' ', $business['phrase']));
                $phrases = array_merge($phrases, $this->phrasesIn($business, $padded));
            }
        }

        if ($landlordIds !== []) {
            return ['host' => $this->host('business', $landlordIds, [], $names, $typed), 'miss' => null, 'covered' => $covered, 'phrases' => $phrases];
        }

        $propertyIds = [];
        foreach ($index['listings'] as $listing) {
            if ($this->matches($listing, $padded, $tokens, $minLength, partial: false)) {
                $propertyIds[] = $listing['property_id'];
                $names[] = $listing['name'];
                $covered = array_merge($covered, $listing['words'], explode(' ', $listing['phrase']));
                $phrases = array_merge($phrases, $this->phrasesIn($listing, $padded));
            }
        }

        if ($propertyIds !== []) {
            return ['host' => $this->host('listing', [], $propertyIds, $names, null), 'miss' => null, 'covered' => $covered, 'phrases' => $phrases];
        }

        return ['host' => null, 'miss' => $typed, 'covered' => $covered, 'phrases' => []];
    }

    /**
     * Re-checks a posted-back `host` against the current index: ids must belong to a live business or
     * listing, and the name always comes from our table, never from the request.
     *
     * @return ?array<string, mixed>
     */
    public function validateHost(mixed $raw): ?array
    {
        if (! is_array($raw)) {
            return null;
        }

        $index = $this->index();
        $landlordIds = array_values(array_intersect(array_map('intval', (array) ($raw['landlord_ids'] ?? [])), array_column($index['businesses'], 'landlord_id')));
        $propertyIds = array_values(array_intersect(array_map('intval', (array) ($raw['property_ids'] ?? [])), array_column($index['listings'], 'property_id')));

        $names = [];
        foreach ($index['businesses'] as $business) {
            if (in_array($business['landlord_id'], $landlordIds, true)) {
                $names[] = $business['name'];
            }
        }
        foreach ($index['listings'] as $listing) {
            if (in_array($listing['property_id'], $propertyIds, true)) {
                $names[] = $listing['name'];
            }
        }

        if ($names === []) {
            return null;
        }

        $typed = is_string($raw['typed'] ?? null) ? Landmarks::normalize(mb_substr(strip_tags($raw['typed']), 0, 60)) : null;

        return $this->host($landlordIds !== [] ? 'business' : 'listing', $landlordIds, $landlordIds !== [] ? [] : $propertyIds, $names, $typed ?: null);
    }

    /** Clears the cached index (tests, and after a business is renamed). */
    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /** @param  list<string>  $names */
    private function host(string $kind, array $landlordIds, array $propertyIds, array $names, ?string $typed): array
    {
        $names = array_values(array_unique($names));
        $nameWords = array_filter(explode(' ', Landmarks::normalize(implode(' ', $names))));
        $name = count($names) > 2 ? $names[0] . ' and ' . (count($names) - 1) . ' more' : implode(' and ', $names);

        return [
            'kind' => $kind,
            'landlord_ids' => array_values(array_unique($landlordIds)),
            'property_ids' => array_values(array_unique($propertyIds)),
            'name' => $name,
            // What the tenant typed after the marker, only when part of it is not in the name we matched
            // ("maria santos" vs Santos Rentals Cebu): that is a near miss and the page says so. "kang lozada"
            // for Lozada Urban Living is just the name.
            'typed' => $typed !== null && array_diff(explode(' ', $typed), $nameWords) !== [] ? $typed : null,
        ];
    }

    /** @param  array<string, int>  $tokens */
    private function matches(array $entry, string $padded, array $tokens, int $minLength, bool $partial): bool
    {
        foreach ($entry['phrases'] as $phrase) {
            if ($phrase !== '' && str_contains($padded, ' ' . $phrase . ' ')) {
                return true;
            }
        }

        if (! $partial || $entry['words'] === []) {
            return false;
        }

        foreach ($entry['words'] as $word) {
            if (! isset($tokens[$word])) {
                return false;
            }
        }

        return count($entry['words']) > 1 || strlen($entry['words'][0]) >= $minLength;
    }

    /** The entry's phrases that appear whole in the sentence (a distinctive-word-only match has none). */
    private function phrasesIn(array $entry, string $padded): array
    {
        return array_values(array_filter($entry['phrases'], fn ($phrase) => $phrase !== '' && str_contains($padded, ' ' . $phrase . ' ')));
    }

    /**
     * "places listed by tudtud boarding houses": the keyword reading also took "boarding houses" as a
     * property-type filter, but those words are part of the business name. Reads the name alone and the
     * sentence without the name, and drops every filter that only the name produced. "boarding houses by
     * Tudtud Boarding Houses" keeps the type, because the rest of the sentence asks for it too.
     *
     * @param  list<string>  $phrases  the matched name phrases, normalized
     */
    private function withoutNameFilters(string $query, array $intent, array $phrases): array
    {
        $rest = ' ' . Landmarks::normalize($query) . ' ';
        foreach ($phrases as $phrase) {
            $rest = str_replace(' ' . $phrase . ' ', ' ', $rest);
        }

        $inName = $this->keywords->parse(implode(' ', $phrases));
        $inRest = $this->keywords->parse(trim($rest));

        if (($inName['property_type'] ?? null) !== null
            && $intent['property_type'] === $inName['property_type']
            && ($inRest['property_type'] ?? null) !== $inName['property_type']) {
            // The reader keeps the first type word it sees, which was the name's. Use what the rest asks for.
            $intent['property_type'] = $inRest['property_type'] ?? null;
        }

        foreach (['amenities', 'rules'] as $field) {
            $onlyName = array_diff((array) ($inName[$field] ?? []), (array) ($inRest[$field] ?? []));
            $intent[$field] = array_values(array_diff($intent[$field], $onlyName));
        }

        if (($inName['for'] ?? null) !== null && $intent['for'] === $inName['for'] && ($inRest['for'] ?? null) !== $inName['for']) {
            $intent['for'] = null;
        }

        $nameArea = $inName['location']['value'] ?? null;
        if ($nameArea !== null && ($intent['location']['value'] ?? null) === $nameArea && ($inRest['location']['value'] ?? null) !== $nameArea) {
            $intent['location'] = null;
        }

        return $intent;
    }

    /**
     * The name after a marker ("ni maria santos" -> "maria santos"), cut at the first word that starts the
     * rest of the sentence. Null when there is no marker or what follows is not plausibly a name.
     */
    private function typedName(string $text): ?string
    {
        $markers = implode('|', array_map(fn ($m) => preg_quote($m, '/'), self::MARKERS));
        if (! preg_match('/(?:^|\s)(?:' . $markers . ')\s+(.+)$/', $text, $m)) {
            return null;
        }

        $name = [];
        foreach (explode(' ', $m[1]) as $token) {
            if ($token === '' || in_array($token, self::BOUNDARY, true) || preg_match('/\d/', $token) || count($name) >= 4) {
                break;
            }
            $name[] = $token;
        }

        $noise = $this->noiseWords();
        $real = array_filter($name, fn ($t) => strlen($t) >= 2 && ! in_array($t, self::NOT_A_NAME, true) && ! isset($noise[$t]));

        // "ni" before a place or a kind of place ("kwarto ni IT Park") is not a name.
        return $real === [] ? null : implode(' ', $name);
    }

    /**
     * @return array{businesses: list<array<string, mixed>>, listings: list<array<string, mixed>>}
     */
    private function index(): array
    {
        return Cache::remember(self::CACHE_KEY, now()->addMinutes(10), function () {
            $noise = $this->noiseWords();

            $properties = Property::live()
                ->whereHas('units', fn ($q) => $q->where('availability_status', 'Available')->where('verification_status', 'Approved'))
                ->with('landlord.rentalBusiness:business_id,landlord_id,business_name')
                ->get(['property_id', 'landlord_id', 'title']);

            $businesses = [];
            $listings = [];

            foreach ($properties->groupBy('landlord_id') as $landlordId => $group) {
                $name = trim((string) $group->first()->landlord?->rentalBusiness?->business_name);
                if ($name === '') {
                    continue;
                }
                $phrase = Landmarks::normalize($name);
                $businesses[] = [
                    'landlord_id' => (int) $landlordId,
                    'name' => $name,
                    'phrase' => $phrase,
                    'phrases' => [$phrase],
                    'words' => $this->distinctive($phrase, $noise),
                ];
            }

            foreach ($properties as $property) {
                $title = trim((string) $property->title);
                $words = $this->distinctive(Landmarks::normalize($title), $noise);
                if ($title === '' || $words === []) {
                    continue; // a title made only of place and type words says nothing about who or which
                }
                $phrases = array_values(array_unique(array_filter([
                    Landmarks::normalize($title),
                    Landmarks::normalize(explode(',', $title)[0]),
                ])));
                $listings[] = [
                    'property_id' => (int) $property->property_id,
                    'name' => $title,
                    'phrase' => $phrases[0],
                    'phrases' => $phrases,
                    'words' => $words,
                ];
            }

            return ['businesses' => $businesses, 'listings' => $listings];
        });
    }

    /** @return list<string> the words of a normalized name that are neither generic nor a place */
    private function distinctive(string $phrase, array $noise): array
    {
        return array_values(array_filter(
            explode(' ', $phrase),
            fn ($word) => strlen($word) >= 3 && ! isset($noise[$word]),
        ));
    }

    /**
     * Words that say what or where a place is rather than whose: the generic list, property types, every
     * city, barangay and landmark name. Cheap: the area list it reads is cached.
     *
     * @return array<string, true>
     */
    private function noiseWords(): array
    {
        $sources = array_merge(
            self::GENERIC,
            Vocabulary::propertyTypes(),
            (array) config('cebu.lgus', []),
            array_column(Property::searchLocations(), 'label'),
            ...array_map(fn ($l) => array_merge([$l['name']], $l['aliases']), Landmarks::all()),
        );

        $noise = [];
        foreach ($sources as $source) {
            foreach (explode(' ', Landmarks::normalize((string) $source)) as $word) {
                if ($word !== '') {
                    $noise[$word] = true;
                }
            }
        }

        return $noise;
    }
}
