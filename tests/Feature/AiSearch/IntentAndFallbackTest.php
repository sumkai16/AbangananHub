<?php

namespace Tests\Feature\AiSearch;

use App\Services\AiSearch\IntentValidator;
use App\Services\AiSearch\KeywordFallback;

class IntentAndFallbackTest extends AiSearchTestCase
{
    private function validate(array $raw): array
    {
        return app(IntentValidator::class)->validate($raw);
    }

    public function test_unknown_values_are_dropped_and_prices_clamped(): void
    {
        $intent = $this->validate([
            'property_type' => 'Castle',
            'amenities' => ['Wi-Fi', 'Helipad', 'wi-fi'],
            'rules' => ['pets', 'drugs'],
            'price_max' => 5000, 'price_min' => 50,
            'for' => 'Aliens', 'language' => 'klingon',
        ]);

        $this->assertNull($intent['property_type']);
        $this->assertSame(['Wi-Fi'], $intent['amenities']);
        $this->assertSame(['pets'], $intent['rules']);
        $this->assertNull($intent['price_min']);   // below ₱500
        $this->assertSame(5000, $intent['price_max']);
        $this->assertNull($intent['for']);
        $this->assertSame('en', $intent['language']);
    }

    public function test_swapped_min_and_max_are_corrected(): void
    {
        $intent = $this->validate(['price_min' => 9000, 'price_max' => 3000]);

        $this->assertSame([3000, 9000], [$intent['price_min'], $intent['price_max']]);
    }

    public function test_places_resolve_to_landmark_city_or_free_text(): void
    {
        $landmark = $this->validate(['location' => ['kind' => 'text', 'value' => 'USC TC']])['location'];
        $this->assertSame(['landmark', 'usc_talamban'], [$landmark['kind'], $landmark['value']]);

        $city = $this->validate(['location' => ['kind' => 'city', 'value' => 'mandaue']])['location'];
        $this->assertSame(['city', 'Mandaue City'], [$city['kind'], $city['value']]);

        $text = $this->validate(['location' => ['kind' => 'barangay', 'value' => 'Some Unknown Place']])['location'];
        $this->assertSame('text', $text['kind']);
    }

    public function test_a_validated_intent_survives_a_second_validation(): void
    {
        $once = $this->validate(['location' => ['kind' => 'landmark', 'value' => 'it_park'], 'amenities' => ['Wi-Fi'], 'price_max' => 5000, 'rules' => ['pets']]);

        $this->assertSame($once, $this->validate($once));
    }

    public function test_keyword_fallback_reads_the_common_cases(): void
    {
        $this->listing('Anywhere', ['barangay' => 'Talamban']);
        $parse = fn (string $q) => $this->validate((new KeywordFallback())->parse($q));

        $a = $parse('Boarding house near USC Talamban with wifi under 5k, may pusa ako');
        $this->assertSame('usc_talamban', $a['location']['value']);
        $this->assertSame('Boarding House', $a['property_type']);
        $this->assertSame(5000, $a['price_max']);
        $this->assertContains('Wi-Fi', $a['amenities']);
        $this->assertContains('pets', $a['rules']);

        $b = $parse('Apartment sa Mandaue ubos sa ₱4,000 naay parking');
        $this->assertSame('Apartment', $b['property_type']);
        $this->assertSame(4000, $b['price_max']);
        $this->assertContains('Parking Space', $b['amenities']);

        $c = $parse('condo for women starting 10k');
        $this->assertSame(['Condominium', 'Women', 10000, null], [$c['property_type'], $c['for'], $c['price_min'], $c['price_max']]);
    }

    public function test_language_is_detected_from_the_words_typed(): void
    {
        $detect = fn (string $q) => \App\Services\AiSearch\LanguageDetector::detect($q);

        $this->assertSame('en', $detect('Room near USC Talamban with Wi-Fi under 5k'));
        $this->assertSame('ceb', $detect('Naa bay apartment sa Mandaue ubos sa 4k, naay parking ug aircon?'));
        $this->assertSame('ceb', $detect('Condo duol sa IT Park nga naay swimming pool, 20k hangtod'));
        $this->assertSame('tl', $detect('Bedspace para sa babae malapit sa CIT-U, may aircon'));
        $this->assertSame('tl', $detect('Murang boarding house malapit sa Colon, may wifi'));
        $this->assertNull($detect('apartment'));
    }
}
