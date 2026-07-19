<?php

namespace Database\Seeders;

use App\Models\Amenity;
use Illuminate\Database\Seeder;

class AmenitySeeder extends Seeder
{
    public function run(): void
    {
        $amenities = [
            // Connectivity & power
            'Wi-Fi',
            'Air Conditioning',
            'Electric Fan',
            'Backup Generator',
            'Submeter (Electricity)',
            'Submeter (Water)',

            // Kitchen & laundry
            'Shared Kitchen',
            'Private Kitchen',
            'Refrigerator',
            'Microwave',
            'Water Dispenser',
            'Washing Machine',
            'Laundry Area',

            // Bath & comfort
            'Private Bathroom',
            'Shared Bathroom',
            'Hot Shower',
            'Bed Included',
            'Study Table',
            'Wardrobe / Cabinet',

            // Building & access
            'Elevator',
            'Parking Space',
            'Motorcycle Parking',
            'CCTV',
            '24/7 Security',
            'Gated Entrance',
            'Balcony',
            'Rooftop Access',

            // Rules & extras
            'Pet Friendly',
            'Curfew',
            'Visitors Allowed',
            'Near Public Transport',
            'Near School / University',
            'Near Market / Grocery',
        ];

        foreach ($amenities as $name) {
            Amenity::firstOrCreate(['amenity_name' => $name]);
        }
    }
}
