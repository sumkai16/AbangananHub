<?php

namespace Database\Seeders;

use App\Models\Amenity;
use Illuminate\Database\Seeder;

class AmenitySeeder extends Seeder
{
    public function run(): void
    {
        // category => [amenity_name => scope]. 'both' shows on both the
        // property and unit forms. Keyed on amenity_name (already UNIQUE) via
        // updateOrCreate below, so re-seeding never shifts an amenity_id and
        // orphans the unit_amenities/property_amenities pivot rows.
        $amenities = [
            'Connectivity & power' => [
                'Wi-Fi'                  => 'both',
                'Air Conditioning'       => 'unit',
                'Electric Fan'           => 'unit',
                'Backup Generator'       => 'property',
                'Submeter (Electricity)' => 'unit',
                'Submeter (Water)'       => 'unit',
            ],
            'Kitchen & laundry' => [
                'Shared Kitchen'    => 'property',
                'Private Kitchen'   => 'unit',
                'Refrigerator'      => 'unit',
                'Microwave'         => 'unit',
                'Water Dispenser'   => 'property',
                'Washing Machine'   => 'property',
                'Laundry Area'      => 'property',
            ],
            'Bath & comfort' => [
                'Private Bathroom'    => 'unit',
                'Shared Bathroom'     => 'unit',
                'Hot Shower'          => 'unit',
                'Bed Included'        => 'unit',
                'Study Table'         => 'unit',
                'Wardrobe / Cabinet'  => 'unit',
            ],
            'Building & access' => [
                'Elevator'            => 'property',
                'Parking Space'       => 'property',
                'Motorcycle Parking'  => 'property',
                'CCTV'                => 'property',
                '24/7 Security'       => 'property',
                'Gated Entrance'      => 'property',
                'Balcony'             => 'unit',
                'Rooftop Access'      => 'property',
            ],
            'Rules & extras' => [
                'Pet Friendly'              => 'property',
                'Curfew'                    => 'property',
                'Visitors Allowed'          => 'property',
                'Near Public Transport'     => 'property',
                'Near School / University'  => 'property',
                'Near Market / Grocery'     => 'property',
            ],
        ];

        foreach ($amenities as $category => $items) {
            foreach ($items as $name => $scope) {
                Amenity::updateOrCreate(
                    ['amenity_name' => $name],
                    ['scope' => $scope, 'category' => $category],
                );
            }
        }
    }
}
