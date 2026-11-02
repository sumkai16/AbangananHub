<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * A hand-edited Browse URL must never 500. These inputs were never SQL injection (values are
 * bound), but they crashed the page: a string where an array was expected, or text in a number.
 */
class BrowseHostileInputTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        config(['broadcasting.default' => 'null']);
    }

    /** @return array<string, array{string}> */
    public static function hostileQueries(): array
    {
        return [
            'amenities as a string'       => ['amenities=abc'],
            'amenities as nested arrays'  => ['amenities[][x]=1'],
            'amenities with text ids'     => ['amenities[]=1&amenities[]=abc'],
            'price_max with SQL in it'    => ['price_max=1%20OR%20SLEEP(5)'],
            'price_min as an array'       => ['price_min[]=5'],
            'negative price'              => ['price_max=-5'],
            'type as an array'            => ['type[]=x'],
            'location as an array'        => ['location[]=x'],
            'sort with SQL in it'         => ['sort=price_low,(SELECT%20SLEEP(5))'],
            'quote in location'           => ['location=%27%20OR%20%271%27%3D%271'],
        ];
    }

    #[DataProvider('hostileQueries')]
    public function test_browse_page_survives_hostile_input(string $query): void
    {
        $this->get('/properties?' . $query)->assertOk();
    }

    #[DataProvider('hostileQueries')]
    public function test_count_endpoint_survives_hostile_input(string $query): void
    {
        $this->getJson('/properties/count?' . $query)->assertOk()->assertJsonStructure(['count']);
    }

    #[DataProvider('hostileQueries')]
    public function test_api_listing_survives_hostile_input(string $query): void
    {
        $this->getJson('/api/v1/properties?' . $query)->assertOk();
    }

    public function test_valid_filters_are_left_alone(): void
    {
        $this->get('/properties?price_min=1000&price_max=5000&amenities[]=1&amenities[]=2')
            ->assertOk()
            ->assertSee('₱1,000', false);
    }
}
