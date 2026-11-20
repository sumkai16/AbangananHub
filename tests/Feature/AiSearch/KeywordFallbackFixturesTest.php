<?php

namespace Tests\Feature\AiSearch;

use App\Services\AiSearch\IntentValidator;
use App\Services\AiSearch\KeywordFallback;

/**
 * The keyword fallback answers when the AI is down or over its daily cap, so it has to read the same sentences the
 * AI is scored on: English, Tagalog, Bisaya and the mixes of them ("lima ka libo", "kinse mil", "walay curfew").
 * Runs every sentence in tests/fixtures/ai-search-cases.json without any AI call and checks the fields plain
 * matching can read. Location is left out: it depends on which areas have listings in the database.
 */
class KeywordFallbackFixturesTest extends AiSearchTestCase
{
    private const CHECKED = ['property_type', 'price_min', 'price_max', 'amenities', 'rules', 'for', 'furnishing', 'sort', 'bedrooms_min', 'deposit_max', 'occupants'];

    protected function setUp(): void
    {
        parent::setUp();
        config(['ai_search.features.sort' => true]);
    }

    public function test_the_fallback_reads_every_fixture_sentence(): void
    {
        $cases = json_decode(file_get_contents(base_path('tests/fixtures/ai-search-cases.json')), true);
        $this->assertGreaterThan(100, count($cases));

        $fallback = app(KeywordFallback::class);
        $validator = app(IntentValidator::class);
        $misses = [];

        foreach ($cases as $case) {
            $intent = $validator->validate($fallback->parse($case['q']));

            foreach (self::CHECKED as $field) {
                if (! array_key_exists($field, $case['expect'])) {
                    continue;
                }
                $want = $case['expect'][$field];
                $ok = in_array($field, ['amenities', 'rules'], true)
                    ? array_diff((array) $want, $intent[$field]) === []
                    : ($intent[$field] ?? null) === $want;

                $ok || $misses[] = $case['q'] . " -> $field wanted " . json_encode($want, JSON_UNESCAPED_UNICODE) . ', got ' . json_encode($intent[$field] ?? null, JSON_UNESCAPED_UNICODE);
            }
        }

        $this->assertSame([], $misses, implode("\n", $misses));
    }

    /** @dataProvider amounts */
    public function test_amounts_in_every_way_people_write_them(string $sentence, ?int $min, ?int $max): void
    {
        $intent = app(KeywordFallback::class)->parse($sentence);

        $this->assertSame($min, $intent['price_min'] ?? null, $sentence);
        $this->assertSame($max, $intent['price_max'] ?? null, $sentence);
    }

    public static function amounts(): array
    {
        return [
            'k' => ['room 5k', null, 5000],
            'decimal k' => ['room 4.5k', null, 4500],
            'peso sign and comma' => ['room ₱5,000', null, 5000],
            'plain' => ['room 5000', null, 5000],
            'tagalog words' => ['kwarto limang libo pababa', null, 5000],
            'bisaya words' => ['kwarto lima ka libo hangtod', null, 5000],
            'spanish words' => ['kwarto singko mil', null, 5000],
            'kinse mil' => ['apartment kinse mil', null, 15000],
            'range with to' => ['room 3k to 4.5k', 3000, 4500],
            'range with dash' => ['room ₱3,000-₱5,000', 3000, 5000],
            'range in words' => ['boarding house tulo ka libo ngadto sa lima ka libo', 3000, 5000],
            'above' => ['apartment above 8000', 8000, null],
            'k up' => ['apartment 12k up', 12000, null],
            'bedrooms are not money' => ['house 2 bedrooms', null, null],
            'no amount' => ['room near colon', null, null],
        ];
    }

    /** @dataProvider negatedRules */
    public function test_a_rule_the_tenant_does_not_want_is_not_a_requirement(string $sentence, string $rule, bool $expected): void
    {
        $rules = app(KeywordFallback::class)->parse($sentence)['rules'];

        $this->assertSame($expected, in_array($rule, $rules, true), $sentence);
    }

    public static function negatedRules(): array
    {
        return [
            'pets wanted' => ['apartment pet friendly', 'pets', true],
            'no pets' => ['apartment no pets', 'pets', false],
            'bawal ang pets' => ['bawal ang pets apartment sa Lahug', 'pets', false],
            'wala koy pet' => ['kwarto wala koy pet', 'pets', false],
            'no smoking' => ['room no smoking', 'smoking', false],
            'no curfew is a requirement' => ['room walay curfew', 'curfew', true],
            'bisita' => ['kwarto pwede magdala ng bisita', 'overnight', true],
        ];
    }

    public function test_motorcycle_parking_is_not_also_a_car_space(): void
    {
        $amenities = app(KeywordFallback::class)->parse('boarding house motorcycle parking')['amenities'];

        $this->assertContains('Motorcycle Parking', $amenities);
        $this->assertNotContains('Parking Space', $amenities);
    }
}
