<?php

namespace Tests\Feature\AiSearch;

use App\Models\PropertyUnit;
use App\Services\AiSearch\AiSearchService;
use App\Services\AiSearch\CandidateFinder;
use App\Services\AiSearch\ChipBuilder;
use App\Services\AiSearch\IntentValidator;
use App\Services\AiSearch\KeywordFallback;

/** Phase 1 of plans/ai-search-maps-and-haiku.md: sort words, bedrooms, deposit and group size. */
class SortAndUnitFiltersTest extends AiSearchTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['ai_search.features.sort' => true]);
    }

    private function unit(int $propertyId, array $attrs): void
    {
        PropertyUnit::where('property_id', $propertyId)->update($attrs);
    }

    private function validate(array $raw): array
    {
        return app(IntentValidator::class)->validate($raw);
    }

    private function titles(array $found): array
    {
        return $found['rows']->pluck('property.title')->all();
    }

    public function test_the_validator_keeps_only_known_sorts_and_sane_numbers(): void
    {
        $i = $this->validate(['sort' => 'price_asc', 'bedrooms_min' => 99, 'deposit_max' => -5]);
        $this->assertSame('price_asc', $i['sort']);
        $this->assertSame(6, $i['bedrooms_min']);
        $this->assertSame(0, $i['deposit_max']);

        $this->assertNull($this->validate(['sort' => 'DROP TABLE'])['sort']);
        $this->assertNull($this->validate(['sort' => 'nearest'])['sort'], 'nearest needs a place with coordinates');
        $this->assertSame('nearest', $this->validate(['sort' => 'nearest', 'location' => ['kind' => 'landmark', 'value' => 'it_park']])['sort']);
    }

    public function test_with_the_feature_off_the_new_fields_are_ignored(): void
    {
        config(['ai_search.features.sort' => false]);

        $i = $this->validate(['sort' => 'price_asc', 'bedrooms_min' => 2, 'deposit_max' => 0]);

        $this->assertNull($i['sort']);
        $this->assertNull($i['bedrooms_min']);
        $this->assertNull($i['deposit_max']);
    }

    public function test_a_sort_alone_is_a_search_not_an_empty_one(): void
    {
        $this->assertFalse(IntentValidator::isEmpty($this->validate(['sort' => 'newest'])));
        $this->assertFalse(IntentValidator::isEmpty($this->validate(['occupants' => 2])));
        $this->assertTrue(IntentValidator::isEmpty($this->validate([])));
    }

    public function test_cheapest_and_most_expensive_reorder_inside_the_exact_group(): void
    {
        $this->listing('Mid Rooms', [], 4000);
        $this->listing('Low Rooms', [], 2500);
        $this->listing('High Rooms', [], 6000);
        $finder = app(CandidateFinder::class);

        $asc = $finder->find($this->validate(['property_type' => 'Boarding House', 'sort' => 'price_asc']));
        $desc = $finder->find($this->validate(['property_type' => 'Boarding House', 'sort' => 'price_desc']));

        $this->assertSame(['Low Rooms', 'Mid Rooms', 'High Rooms'], $this->titles($asc));
        $this->assertSame(['High Rooms', 'Mid Rooms', 'Low Rooms'], $this->titles($desc));
    }

    public function test_a_sort_never_puts_a_close_match_ahead_of_an_exact_one(): void
    {
        $this->listing('Exact Pricey', [], 4800);
        $this->listing('Over Budget Cheap', [], 5200); // only a close match for "under 5000"

        $found = app(CandidateFinder::class)->find($this->validate(['property_type' => 'Boarding House', 'price_max' => 5000, 'sort' => 'price_asc']));

        $this->assertSame(['Exact Pricey', 'Over Budget Cheap'], $this->titles($found));
    }

    public function test_bedrooms_deposit_and_group_size_are_checked_on_one_unit(): void
    {
        $studio = $this->listing('Studio Flat', ['property_type' => 'Apartment'], 8000);
        $two = $this->listing('Two Bedroom Flat', ['property_type' => 'Apartment'], 12000);
        $this->unit($studio->property_id, ['bedrooms' => 1, 'security_deposit' => 8000, 'occupancy_limit' => 2]);
        $this->unit($two->property_id, ['bedrooms' => 2, 'security_deposit' => 0, 'occupancy_limit' => 4]);
        $finder = app(CandidateFinder::class);

        // One exact match is fewer than the minimum, so the other flat comes along as a close one, after it.
        foreach ([['bedrooms_min' => 2], ['deposit_max' => 0], ['occupants' => 4]] as $filter) {
            $found = $finder->find($this->validate(['property_type' => 'Apartment'] + $filter));
            $this->assertSame('Two Bedroom Flat', $found['rows']->first()['property']->title, json_encode($filter));
            $this->assertSame(1, $found['exact_count'], json_encode($filter));
            $this->assertNotEmpty($found['rows']->last()['misses'], json_encode($filter));
        }
    }

    public function test_group_size_filters_now_that_the_ai_reads_it(): void
    {
        // Found while planning: the AI filled `occupants` and the finder ignored it.
        $solo = $this->listing('Solo Room', [], 3000);
        $this->unit($solo->property_id, ['occupancy_limit' => 1]);
        $pair = $this->listing('Pair Room', [], 3500);
        $this->unit($pair->property_id, ['occupancy_limit' => 2]);

        $found = app(CandidateFinder::class)->find($this->validate(['property_type' => 'Boarding House', 'occupants' => 2]));

        $this->assertSame('Pair Room', $found['rows']->first()['property']->title);
        $this->assertSame(1, $found['exact_count']);
    }

    public function test_the_unit_filters_relax_in_one_step_and_say_what_each_place_misses(): void
    {
        $one = $this->listing('One Bedroom Flat', ['property_type' => 'Apartment'], 8000);
        $this->unit($one->property_id, ['bedrooms' => 1]);

        $found = app(CandidateFinder::class)->find($this->validate(['property_type' => 'Apartment', 'bedrooms_min' => 3]));

        $this->assertSame(['One Bedroom Flat'], $this->titles($found));
        $this->assertContains('bedrooms, deposit and group size', $found['relaxed']);
        $this->assertSame('Only 1 bedroom', $found['rows']->first()['misses'][0]['text']);
    }

    public function test_the_chips_show_and_remove_the_new_filters(): void
    {
        $chips = app(ChipBuilder::class)->build($this->validate(['sort' => 'rating', 'bedrooms_min' => 2, 'deposit_max' => 0, 'occupants' => 3]));
        $labels = array_column($chips, 'label');

        $this->assertContains('Best rated first', $labels);
        $this->assertContains('2+ bedrooms', $labels);
        $this->assertContains('No deposit', $labels);
        $this->assertContains('Good for 3', $labels);

        $intent = ChipBuilder::remove($this->validate(['sort' => 'rating']), 'sort', null);
        $this->assertNull($intent['sort']);
    }

    public function test_the_summary_says_how_it_was_ordered_in_the_language_asked(): void
    {
        $this->listing('Low Rooms', [], 2500);
        $this->listing('High Rooms', [], 6000);
        $svc = app(AiSearchService::class);

        $en = $svc->results('cheapest', $this->validate(['property_type' => 'Boarding House', 'sort' => 'price_asc', 'language' => 'en']), false);
        $tl = $svc->results('pinakamura', $this->validate(['property_type' => 'Boarding House', 'sort' => 'price_asc', 'language' => 'tl']), false);
        $ceb = $svc->results('pinakabarato', $this->validate(['property_type' => 'Boarding House', 'sort' => 'price_asc', 'language' => 'ceb']), false);

        $this->assertStringContainsString('Cheapest first.', $en['summary']);
        $this->assertStringContainsString('Pinakamura muna.', $tl['summary']);
        $this->assertStringContainsString('Pinakabarato una.', $ceb['summary']);
        $this->assertSame('Low Rooms', $en['rows']->first()['property']->title);
    }

    /** @dataProvider fallbackSentences */
    public function test_the_keyword_fallback_reads_the_same_words(string $sentence, array $expected): void
    {
        $intent = $this->validate(app(KeywordFallback::class)->parse($sentence));

        foreach ($expected as $field => $value) {
            $this->assertSame($value, $intent[$field], "$sentence -> $field");
        }
    }

    public static function fallbackSentences(): array
    {
        return [
            'cheapest' => ['cheapest bedspace in Colon', ['sort' => 'price_asc', 'property_type' => 'Bedspace']],
            'pinakamura' => ['pinakamura nga kwarto sa Mandaue', ['sort' => 'price_asc']],
            'cheap alone is no sort' => ['cheap room near Colon', ['sort' => null]],
            'most expensive' => ['most expensive house in Talisay', ['sort' => 'price_desc', 'property_type' => 'House']],
            'highest rated' => ['highest rated boarding house', ['sort' => 'rating']],
            'newest' => ['newest listings', ['sort' => 'newest']],
            'nearest with a place' => ['nearest bedspace to IT Park', ['sort' => 'nearest', 'property_type' => 'Bedspace']],
            'bedrooms' => ['2 bedroom apartment sa Banilad', ['bedrooms_min' => 2, 'property_type' => 'Apartment']],
            'kwarto in a house' => ['bahay sa Talisay na may 3 kwarto', ['bedrooms_min' => 3, 'property_type' => 'House']],
            'kwarto in a boarding house is no count' => ['boarding house 2 kwarto lang', ['bedrooms_min' => null]],
            'no deposit' => ['walay deposit nga kwarto', ['deposit_max' => 0]],
            'deposit limit is not the rent' => ['apartment 8k, deposit up to 3k', ['deposit_max' => 3000, 'price_max' => 8000]],
            'group of' => ['house good for 4', ['occupants' => 4]],
            'family of' => ['family of 5 house in Naga', ['occupants' => 5]],
            'couple' => ['apartment for a couple', ['occupants' => 2]],
            'for 5k is a budget, not a group' => ['room for 5k', ['occupants' => null, 'price_max' => 5000]],
        ];
    }
}
