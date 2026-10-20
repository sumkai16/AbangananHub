<?php

namespace Tests\Feature\AiSearch;

use App\Models\Amenity;
use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\TestCase;

/** Shared fixtures for the AI search tests: a landlord and a few listings around Talamban and Mandaue. */
abstract class AiSearchTestCase extends TestCase
{
    use CreatesMarketplaceFixtures, DatabaseTransactions;

    protected User $landlord;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush(); // searchLocations()/amenities are cached for minutes; the array store outlives a test
        config(['broadcasting.default' => 'null', 'ai_search.driver' => 'null']);
        $this->landlord = $this->makeLandlord();
    }

    protected function amenity(string $name): Amenity
    {
        return Amenity::firstOrCreate(['amenity_name' => $name], ['scope' => 'both', 'category' => 'Test']);
    }

    /** A live property with one available approved unit. */
    protected function listing(string $title, array $property = [], int $fee = 4500, array $amenities = []): Property
    {
        $p = $this->makeProperty($this->landlord, array_merge([
            'title' => $title,
            'property_type' => 'Boarding House',
            'address' => 'Talamban, Cebu City, Cebu',
            'city_municipality' => 'Cebu City',
            'barangay' => 'Talamban',
            'latitude' => 10.3532,
            'longitude' => 123.9120,
        ], $property));
        $unit = $this->makeUnit($p, ['rental_fee' => $fee]);
        foreach ($amenities as $name) {
            $unit->amenities()->attach($this->amenity($name)->amenity_id);
        }

        return $p->fresh();
    }
}
