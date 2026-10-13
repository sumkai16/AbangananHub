<?php

namespace Tests\Feature;

use App\Models\Property;
use App\Models\PropertyUnit;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\TestCase;

/**
 * The budget card in the Filters modal: its quick ranges, slider steps and
 * histogram are derived from the units actually available, not a fixed table.
 *
 * Asserts invariants rather than exact figures, so it holds whatever else the
 * test database already contains.
 */
class BudgetFilterTest extends TestCase
{
    use CreatesMarketplaceFixtures, DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        config(['broadcasting.default' => 'null']);

        $landlord = $this->makeLandlord();
        foreach ([1500, 2500, 3500, 5000, 8000, 22000] as $i => $fee) {
            $property = $this->makeProperty($landlord, ['title' => "Budget fixture {$i}"]);
            $this->makeUnit($property, ['rental_fee' => $fee]);
        }

        Cache::forget('search.budget_fees');
    }

    public function test_bands_chain_end_to_end_and_counts_match_a_direct_query(): void
    {
        $bands = Property::budgetBands();

        $this->assertGreaterThanOrEqual(3, count($bands));
        $this->assertNull($bands[0]['min']);
        $this->assertNull($bands[array_key_last($bands)]['max']);

        foreach ($bands as $i => $band) {
            if (isset($bands[$i + 1])) {
                $this->assertSame($band['max'], $bands[$i + 1]['min'], 'each range must start where the last one ends');
                $this->assertGreaterThan($band['min'] ?? 0, $band['max']);
            }

            $direct = Property::live()->whereHas('units', function ($q) use ($band) {
                $q->where('availability_status', 'Available')->where('verification_status', 'Approved');
                if ($band['min'] !== null) {
                    $q->where('rental_fee', '>=', $band['min']);
                }
                if ($band['max'] !== null) {
                    $q->where('rental_fee', '<=', $band['max']);
                }
            })->count();

            $this->assertSame($direct, $band['count'], "count for {$band['label']}");
        }

        $this->assertCount(1, array_filter($bands, fn ($b) => $b['popular']));
    }

    public function test_slider_stops_reach_the_dearest_unit_and_the_histogram_lines_up(): void
    {
        $stops = Property::budgetStops();
        $max = (float) PropertyUnit::where('availability_status', 'Available')
            ->where('verification_status', 'Approved')
            ->whereHas('property', fn ($q) => $q->live())
            ->max('rental_fee');

        $this->assertSame(0, $stops[0]);
        $this->assertSame($stops, collect($stops)->sort()->values()->all(), 'stops must ascend');
        $this->assertGreaterThanOrEqual($max, end($stops), 'the top step must cover the dearest unit');
        $this->assertCount(count($stops) - 1, Property::budgetHistogram());
    }

    public function test_budget_lives_in_the_filters_modal_and_not_in_the_search_pill(): void
    {
        $html = $this->get('/properties')->assertOk()->getContent();

        $this->assertStringContainsString('Monthly budget', $html);
        $this->assertStringNotContainsString('Budget (', $html, 'the pill must no longer have a Budget field');
        $this->assertSame(1, substr_count($html, 'name="price_min"'), 'one price_min input: the card');
    }

    public function test_the_ai_search_box_starts_a_fresh_search_and_does_not_carry_the_filters_price(): void
    {
        // The pill used to carry a price chosen in Filters as hidden fields. The described-search box replaces it:
        // the sentence is the whole search (the AI reads any budget from it), so only `q` is submitted.
        $html = $this->get('/properties?price_min=2000&price_max=4000')->assertOk()->getContent();

        preg_match('#<form[^>]*role="search".*?</form>#s', $html, $form);

        $this->assertNotEmpty($form, 'the AI search box is on the page');
        $this->assertStringContainsString('name="q"', $form[0]);
        $this->assertStringNotContainsString('price_min', $form[0]);
        $this->assertStringNotContainsString('price_max', $form[0]);
    }
}
