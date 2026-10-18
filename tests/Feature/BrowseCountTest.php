<?php

namespace Tests\Feature;

use App\Models\Property;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\TestCase;

/**
 * The number on the Filters dialog's "Show N places" button comes from /properties/count,
 * which must give exactly what the Browse page itself would list for the same query.
 */
class BrowseCountTest extends TestCase
{
    use CreatesMarketplaceFixtures, DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        config(['broadcasting.default' => 'null']);
    }

    public function test_count_matches_what_the_browse_page_lists(): void
    {
        $landlord = $this->makeLandlord();
        foreach ([1800, 2600, 9000] as $i => $fee) {
            $this->makeUnit($this->makeProperty($landlord, ['title' => "Count fixture {$i}"]), ['rental_fee' => $fee]);
        }

        foreach ([[], ['price_max' => 3000], ['price_min' => 2000, 'price_max' => 3000], ['price_min' => 99999]] as $query) {
            $expected = Property::browsable()
                ->browseFilters($query + ['location' => null, 'type' => null, 'price_min' => null, 'price_max' => null, 'verified' => false, 'amenities' => [], 'sort' => null,
                    'living' => null, 'for' => null, 'furnishing' => null, 'rules' => []])
                ->paginate(1)->total();

            $this->getJson(route('properties.count', $query))
                ->assertOk()
                ->assertExactJson(['count' => $expected]);
        }
    }

    public function test_count_responds_to_filters(): void
    {
        $landlord = $this->makeLandlord();
        $this->makeUnit($this->makeProperty($landlord), ['rental_fee' => 1800]);

        $all = $this->getJson(route('properties.count'))->json('count');
        $none = $this->getJson(route('properties.count', ['price_min' => 9999999]))->json('count');

        $this->assertGreaterThanOrEqual(1, $all);
        $this->assertSame(0, $none);
    }
}
