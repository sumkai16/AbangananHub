<?php

namespace Tests\Support;

use App\Models\Property;
use App\Models\PropertyUnit;
use App\Models\User;

/**
 * Builds the rows the marketplace tests need, so they don't depend on
 * whatever the database happens to be seeded with. Use inside a test that
 * wraps itself in DatabaseTransactions; the rows roll back with it.
 */
trait CreatesMarketplaceFixtures
{
    protected function makeUserWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    protected function makeLandlord(): User
    {
        return $this->makeUserWithRole('Landlord');
    }

    protected function makeTenant(): User
    {
        return $this->makeUserWithRole('Tenant');
    }

    protected function makeProperty(User $landlord, array $attributes = []): Property
    {
        return Property::create(array_merge([
            'landlord_id'          => $landlord->user_id,
            'title'                => 'Test Property',
            'description'          => 'Fixture property for tests.',
            'property_type'        => 'Apartment',
            'living_arrangement'   => 'Private',
            'occupancy_preference' => 'No Preference',
            'address'              => 'Test Street, Lahug, Cebu City, Cebu',
            'city_municipality'    => 'Cebu City',
            'barangay'             => 'Lahug',
            'latitude'             => 10.3289000,
            'longitude'            => 123.9060000,
            'verification_status'  => 'Approved',
            'publication_status'   => 'Published',
        ], $attributes));
    }

    protected function makeUnit(Property $property, array $attributes = []): PropertyUnit
    {
        return $property->units()->create(array_merge([
            'unit_label'          => 'Unit 1',
            'unit_type'           => 'Studio',
            'floor'               => '1',
            'bedrooms'            => 1,
            'bathrooms'           => 1,
            'floor_area_sqm'      => 25,
            'furnishing_status'   => 'Unfurnished',
            'is_furnished'        => false,
            'rental_fee'          => 5000,
            'security_deposit'    => 5000,
            'occupancy_limit'     => 2,
            'availability_status' => 'Available',
            'verification_status' => 'Approved',
        ], $attributes));
    }
}
