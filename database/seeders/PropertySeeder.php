<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Property;

class PropertySeeder extends Seeder
{
    public function run(): void
    {
        $landlord = User::where('email', 'landlord@abangananhub.com')->first();

        $properties = [
            [
                'title'               => 'Cozy Bedspace in Labangon',
                'description'         => 'Clean and secure bedspace in a quiet residential area in Labangon, Cebu City. Shared comfort room, with 24/7 security and monthly cleaning service included.',
                'property_type'       => 'Bedspace',
                'address'             => 'Labangon, Cebu City, Cebu',
                'latitude'            => 10.2970,
                'longitude'           => 123.8990,
                'rental_fee'          => 2500,
                'occupancy_limit'     => 4,
                'availability_status' => 'Available',
                'verification_status' => 'Approved',
            ],
            [
                'title'               => 'Private Room Near USC Main',
                'description'         => 'Furnished private room near University of San Carlos. Ideal for students. With WiFi, electric fan, and shared kitchen access.',
                'property_type'       => 'Room',
                'address'             => 'P. del Rosario St., Cebu City, Cebu',
                'latitude'            => 10.3000,
                'longitude'           => 123.8980,
                'rental_fee'          => 4500,
                'occupancy_limit'     => 1,
                'availability_status' => 'Available',
                'verification_status' => 'Approved',
            ],
            [
                'title'               => 'Studio Apartment in IT Park',
                'description'         => 'Modern studio unit inside Cebu IT Park. Fully furnished with air conditioning, WiFi, and 24/7 security. Walking distance to restaurants and offices.',
                'property_type'       => 'Apartment',
                'address'             => 'Cebu IT Park, Apas, Cebu City, Cebu',
                'latitude'            => 10.3310,
                'longitude'           => 123.9050,
                'rental_fee'          => 12000,
                'occupancy_limit'     => 2,
                'availability_status' => 'Available',
                'verification_status' => 'Approved',
            ],
            [
                'title'               => 'Affordable Room in Mandaue',
                'description'         => 'Budget-friendly room for rent in Mandaue City. With ceiling fan, personal cabinet, and shared bathroom. Near public transport and wet market.',
                'property_type'       => 'Room',
                'address'             => 'Bakilid, Mandaue City, Cebu',
                'latitude'            => 10.3540,
                'longitude'           => 123.9350,
                'rental_fee'          => 3000,
                'occupancy_limit'     => 1,
                'availability_status' => 'Available',
                'verification_status' => 'Approved',
            ],
            [
                'title'               => '2-Bedroom Apartment in Banilad',
                'description'         => 'Spacious 2-bedroom apartment in Banilad, Cebu City. Comes with refrigerator, washing machine, and parking space. Quiet subdivision setting.',
                'property_type'       => 'Apartment',
                'address'             => 'Banilad, Cebu City, Cebu',
                'latitude'            => 10.3450,
                'longitude'           => 123.8980,
                'rental_fee'          => 18000,
                'occupancy_limit'     => 4,
                'availability_status' => 'Available',
                'verification_status' => 'Approved',
            ],
            [
                'title'               => 'Bedspace for Female in Punta Princesa',
                'description'         => 'Female-only bedspace in a safe and clean boarding house in Punta Princesa. With electric fan, locker, and shared bathroom. Curfew strictly enforced.',
                'property_type'       => 'Bedspace',
                'address'             => 'Punta Princesa, Cebu City, Cebu',
                'latitude'            => 10.2880,
                'longitude'           => 123.9050,
                'rental_fee'          => 2000,
                'occupancy_limit'     => 6,
                'availability_status' => 'Available',
                'verification_status' => 'Approved',
            ],
            [
                'title'               => 'Whole House for Rent in Talisay',
                'description'         => '3-bedroom house for rent in a peaceful neighborhood in Talisay City. With carport, dirty kitchen, and garden area. Ideal for families.',
                'property_type'       => 'House',
                'address'             => 'San Isidro, Talisay City, Cebu',
                'latitude'            => 10.2440,
                'longitude'           => 123.8490,
                'rental_fee'          => 22000,
                'occupancy_limit'     => 6,
                'availability_status' => 'Available',
                'verification_status' => 'Approved',
            ],
            [
                'title'               => 'Room for Rent Near SM Seaside',
                'description'         => 'Clean and ventilated room near SM Seaside City Cebu. With built-in cabinet, bed frame, and shared bathroom. Electricity billed separately.',
                'property_type'       => 'Room',
                'address'             => 'SRP, Mambaling, Cebu City, Cebu',
                'latitude'            => 10.2720,
                'longitude'           => 123.8760,
                'rental_fee'          => 4000,
                'occupancy_limit'     => 1,
                'availability_status' => 'Available',
                'verification_status' => 'Approved',
            ],
            [
                'title'               => 'Condo-Type Apartment in Lahug',
                'description'         => 'Condo-style apartment unit in Lahug, Cebu City. With air conditioning, hot and cold shower, fiber WiFi, and CCTV. Near Ayala Center.',
                'property_type'       => 'Apartment',
                'address'             => 'Lahug, Cebu City, Cebu',
                'latitude'            => 10.3280,
                'longitude'           => 123.9010,
                'rental_fee'          => 15000,
                'occupancy_limit'     => 2,
                'availability_status' => 'Reserved',
                'verification_status' => 'Approved',
            ],
            [
                'title'               => 'Bedspace Near Cebu Doctors University',
                'description'         => 'Male bedspace accommodation near Cebu Doctors University. Suitable for medical students. With study table, locker, and 24-hour water supply.',
                'property_type'       => 'Bedspace',
                'address'             => 'Osmena Blvd, Cebu City, Cebu',
                'latitude'            => 10.2940,
                'longitude'           => 123.8930,
                'rental_fee'          => 2800,
                'occupancy_limit'     => 4,
                'availability_status' => 'Available',
                'verification_status' => 'Approved',
            ],
        ];

        foreach ($properties as $data) {
            Property::create(array_merge($data, [
                'landlord_id' => $landlord->user_id,
            ]));
        }
    }
}