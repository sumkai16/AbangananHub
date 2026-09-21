<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Amenity;
use App\Models\User;
use App\Models\Property;
use App\Models\PropertyMedia;
use App\Models\UnitMedia;

class PropertySeeder extends Seeder
{
    public function run(): void
    {
        $landlord = User::where('email', 'landlord@abangananhub.com')->first();

        // ─── House rules pools by property type ──────────────
        $bedspaceRules = [
            'No overnight visitors',
            'Quiet hours from 10:00 PM to 6:00 AM',
            'Keep your bedspace area clean and organized',
            'No smoking inside the premises',
            'No cooking inside the sleeping area',
            'Lights out by 11:00 PM',
            'Personal belongings must be stored in assigned lockers',
            'No pets allowed',
            'Shared bathroom must be cleaned after use',
            'Report maintenance issues to the caretaker immediately',
        ];

        $roomRules = [
            'No smoking inside the room',
            'No pets allowed',
            'Quiet hours from 10:00 PM to 6:00 AM',
            'Visitors allowed until 9:00 PM only',
            'Keep shared areas clean after use',
            'No illegal activities on the premises',
            'Electricity is billed separately based on sub-meter reading',
            'One month advance and one month deposit required',
            'No alterations to the room without landlord approval',
            'Dispose of garbage properly using designated bins',
        ];

        $apartmentRules = [
            'No smoking inside the unit',
            'No pets unless approved in writing by the landlord',
            'Quiet hours from 10:00 PM to 6:00 AM',
            'No subletting or unauthorized occupants',
            'Keep common areas clean and orderly',
            'Report any maintenance issues immediately',
            'Parking is limited to assigned slots only',
            'Garbage must be segregated and disposed of on schedule',
            'No modifications to walls, fixtures, or appliances without approval',
            'Guests staying overnight must be registered with building admin',
        ];

        $houseRules = [
            'No smoking inside the house',
            'No pets unless approved by the landlord',
            'Tenant is responsible for yard maintenance',
            'No illegal activities on the premises',
            'No subletting or sharing with unregistered occupants',
            'Report plumbing or electrical issues immediately',
            'Garbage must be disposed of on designated collection days',
            'Gate must be locked by 10:00 PM',
            'No structural modifications without written consent',
            'Water and electricity are billed separately',
        ];

        $rulesByType = [
            'Bedspace'  => $bedspaceRules,
            'Room'      => $roomRules,
            'Apartment' => $apartmentRules,
            'House'     => $houseRules,
        ];

        // ─── Unit-level interior photo pool ──────────────
        $unitImagePool = [
            'https://images.unsplash.com/photo-1522771739844-6a9f6d5f14af?w=800&q=80',
            'https://images.unsplash.com/photo-1540518614846-7eded433c457?w=800&q=80',
            'https://images.unsplash.com/photo-1616594039964-ae9021a400a0?w=800&q=80',
            'https://images.unsplash.com/photo-1560185007-cde436f6a4d0?w=800&q=80',
            'https://images.unsplash.com/photo-1615874959474-d609969a20ed?w=800&q=80',
            'https://images.unsplash.com/photo-1585412727339-54e4bae3bbf9?w=800&q=80',
            'https://images.unsplash.com/photo-1505693416388-ac5ce068fe85?w=800&q=80',
            'https://images.unsplash.com/photo-1560448204-603b3fc33ddc?w=800&q=80',
            'https://images.unsplash.com/photo-1556909114-f6e7ad7d3136?w=800&q=80',
            'https://images.unsplash.com/photo-1594560913095-8cf34bab82ad?w=800&q=80',
            'https://images.unsplash.com/photo-1560185008-b033106af5c8?w=800&q=80',
            'https://images.unsplash.com/photo-1631049307264-da0ec9d70304?w=800&q=80',
        ];

        $properties = [
            [
                'title'               => 'Cozy Bedspace in Labangon',
                'description'         => 'Clean and secure bedspace in a quiet residential area in Labangon, Cebu City. Shared comfort room, with 24/7 security and monthly cleaning service included.',
                'property_type'       => 'Bedspace',
                'address'             => 'Labangon, Cebu City, Cebu',
                'latitude'            => 10.3013,
                'longitude'           => 123.8837,
                'verification_status' => 'Approved',
                'units' => [
                    ['unit_label' => 'Bed A', 'rental_fee' => 2500, 'occupancy_limit' => 1, 'availability_status' => 'Available'],
                    ['unit_label' => 'Bed B', 'rental_fee' => 2500, 'occupancy_limit' => 1, 'availability_status' => 'Available'],
                    ['unit_label' => 'Bed C', 'rental_fee' => 2200, 'occupancy_limit' => 1, 'availability_status' => 'Available'],
                    ['unit_label' => 'Bed D', 'rental_fee' => 2200, 'occupancy_limit' => 1, 'availability_status' => 'Available'],
                ],
                'media' => [
                    ['media_type' => 'Image', 'media_url' => 'https://images.unsplash.com/photo-1555854877-bab0e564b8d5?w=1200&q=80'],
                    ['media_type' => 'Image', 'media_url' => 'https://images.unsplash.com/photo-1493809842364-78817add7ffb?w=1200&q=80'],
                    ['media_type' => 'Image', 'media_url' => 'https://images.unsplash.com/photo-1484154218962-a197022b5858?w=1200&q=80'],
                ],
            ],
            [
                'title'               => 'Private Room Near USC Main',
                'description'         => 'Furnished private room near University of San Carlos. Ideal for students. With WiFi, electric fan, and shared kitchen access.',
                'property_type'       => 'Room',
                'address'             => 'P. del Rosario St., Cebu City, Cebu',
                'latitude'            => 10.3010,
                'longitude'           => 123.8966,
                'verification_status' => 'Approved',
                'units' => [
                    ['unit_label' => 'Room 1', 'rental_fee' => 4500, 'occupancy_limit' => 1, 'availability_status' => 'Available'],
                    ['unit_label' => 'Room 2', 'rental_fee' => 4000, 'occupancy_limit' => 1, 'availability_status' => 'Available'],
                    ['unit_label' => 'Room 3', 'rental_fee' => 4500, 'occupancy_limit' => 1, 'availability_status' => 'Available'],
                ],
                'media' => [
                    ['media_type' => 'Image', 'media_url' => 'https://images.unsplash.com/photo-1536376072261-38c75010e6c9?w=1200&q=80'],
                    ['media_type' => 'Image', 'media_url' => 'https://images.unsplash.com/photo-1505691938895-1758d7feb511?w=1200&q=80'],
                ],
            ],
            [
                'title'               => 'Studio Apartment in IT Park',
                'description'         => 'Modern studio unit inside Cebu IT Park. Fully furnished with air conditioning, WiFi, and 24/7 security. Walking distance to restaurants and offices.',
                'property_type'       => 'Apartment',
                'address'             => 'Cebu IT Park, Apas, Cebu City, Cebu',
                'latitude'            => 10.3297,
                'longitude'           => 123.9056,
                'verification_status' => 'Approved',
                'units' => [
                    ['unit_label' => 'Unit 101', 'rental_fee' => 12000, 'occupancy_limit' => 2, 'availability_status' => 'Available'],
                    ['unit_label' => 'Unit 102', 'rental_fee' => 12000, 'occupancy_limit' => 2, 'availability_status' => 'Available'],
                    ['unit_label' => 'Unit 201', 'rental_fee' => 14000, 'occupancy_limit' => 2, 'availability_status' => 'Available'],
                    ['unit_label' => 'Unit 202', 'rental_fee' => 14000, 'occupancy_limit' => 2, 'availability_status' => 'Available'],
                ],
                'media' => [
                    ['media_type' => 'Image', 'media_url' => 'https://images.unsplash.com/photo-1522708323590-d24dbb6b0267?w=1200&q=80'],
                    ['media_type' => 'Image', 'media_url' => 'https://images.unsplash.com/photo-1560448204-603b3fc33ddc?w=1200&q=80'],
                    ['media_type' => 'Image', 'media_url' => 'https://images.unsplash.com/photo-1502672260266-1c1ef2d93688?w=1200&q=80'],
                    ['media_type' => 'Image', 'media_url' => 'https://images.unsplash.com/photo-1554995207-c18c203602cb?w=1200&q=80'],
                ],
            ],
            [
                'title'               => 'Affordable Room in Mandaue',
                'description'         => 'Budget-friendly room for rent in Mandaue City. With ceiling fan, personal cabinet, and shared bathroom. Near public transport and wet market.',
                'property_type'       => 'Room',
                'address'             => 'Bakilid, Mandaue City, Cebu',
                'latitude'            => 10.3340,
                'longitude'           => 123.9300,
                'verification_status' => 'Approved',
                'units' => [
                    ['unit_label' => 'Unit 1', 'rental_fee' => 3000, 'occupancy_limit' => 1, 'availability_status' => 'Available'],
                ],
                'media' => [
                    ['media_type' => 'Image', 'media_url' => 'https://images.unsplash.com/photo-1631049307264-da0ec9d70304?w=1200&q=80'],
                    ['media_type' => 'Image', 'media_url' => 'https://images.unsplash.com/photo-1585771724684-38269d6639fd?w=1200&q=80'],
                ],
            ],
            [
                'title'               => '2-Bedroom Apartment in Banilad',
                'description'         => 'Spacious 2-bedroom apartment in Banilad, Cebu City. Comes with refrigerator, washing machine, and parking space. Quiet subdivision setting.',
                'property_type'       => 'Apartment',
                'address'             => 'Banilad, Cebu City, Cebu',
                'latitude'            => 10.3400,
                'longitude'           => 123.9100,
                'verification_status' => 'Approved',
                'units' => [
                    ['unit_label' => 'Unit A', 'rental_fee' => 18000, 'occupancy_limit' => 4, 'availability_status' => 'Available'],
                    ['unit_label' => 'Unit B', 'rental_fee' => 16000, 'occupancy_limit' => 3, 'availability_status' => 'Available'],
                ],
                'media' => [
                    ['media_type' => 'Image', 'media_url' => 'https://images.unsplash.com/photo-1598928506311-c55ded91a20c?w=1200&q=80'],
                    ['media_type' => 'Image', 'media_url' => 'https://images.unsplash.com/photo-1556909114-f6e7ad7d3136?w=1200&q=80'],
                    ['media_type' => 'Image', 'media_url' => 'https://images.unsplash.com/photo-1583608205776-bfd35f0d9f83?w=1200&q=80'],
                ],
            ],
            [
                'title'               => 'Bedspace for Female in Punta Princesa',
                'description'         => 'Female-only bedspace in a safe and clean boarding house in Punta Princesa. With electric fan, locker, and shared bathroom. Curfew strictly enforced.',
                'property_type'       => 'Bedspace',
                'address'             => 'Punta Princesa, Cebu City, Cebu',
                'latitude'            => 10.2970,
                'longitude'           => 123.8770,
                'verification_status' => 'Approved',
                'units' => [
                    ['unit_label' => 'Bed 1', 'rental_fee' => 2000, 'occupancy_limit' => 1, 'availability_status' => 'Available'],
                    ['unit_label' => 'Bed 2', 'rental_fee' => 2000, 'occupancy_limit' => 1, 'availability_status' => 'Available'],
                    ['unit_label' => 'Bed 3', 'rental_fee' => 2000, 'occupancy_limit' => 1, 'availability_status' => 'Available'],
                ],
                'media' => [
                    ['media_type' => 'Image', 'media_url' => 'https://images.unsplash.com/photo-1595526114035-0d45ed16cfbf?w=1200&q=80'],
                    ['media_type' => 'Image', 'media_url' => 'https://images.unsplash.com/photo-1586105251261-72a756497a11?w=1200&q=80'],
                ],
            ],
            [
                'title'               => 'Whole House for Rent in Talisay',
                'description'         => '3-bedroom house for rent in a peaceful neighborhood in Talisay City. With carport, dirty kitchen, and garden area. Ideal for families.',
                'property_type'       => 'House',
                'address'             => 'San Isidro, Talisay City, Cebu',
                'latitude'            => 10.2560,
                'longitude'           => 123.8430,
                'verification_status' => 'Approved',
                'units' => [
                    ['unit_label' => 'Unit 1', 'rental_fee' => 22000, 'occupancy_limit' => 6, 'availability_status' => 'Available'],
                ],
                'media' => [
                    ['media_type' => 'Image', 'media_url' => 'https://images.unsplash.com/photo-1568605114967-8130f3a36994?w=1200&q=80'],
                    ['media_type' => 'Image', 'media_url' => 'https://images.unsplash.com/photo-1570129477492-45c003edd2be?w=1200&q=80'],
                    ['media_type' => 'Image', 'media_url' => 'https://images.unsplash.com/photo-1512917774080-9991f1c4c750?w=1200&q=80'],
                ],
            ],
            [
                'title'               => 'Room for Rent Near SM Seaside',
                'description'         => 'Clean and ventilated room near SM Seaside City Cebu. With built-in cabinet, bed frame, and shared bathroom. Electricity billed separately.',
                'property_type'       => 'Room',
                'address'             => 'SRP, Mambaling, Cebu City, Cebu',
                'latitude'            => 10.2830,
                'longitude'           => 123.8750,
                'verification_status' => 'Approved',
                'units' => [
                    ['unit_label' => 'Room A', 'rental_fee' => 4000, 'occupancy_limit' => 1, 'availability_status' => 'Available'],
                    ['unit_label' => 'Room B', 'rental_fee' => 3800, 'occupancy_limit' => 1, 'availability_status' => 'Available'],
                ],
                'media' => [
                    ['media_type' => 'Image', 'media_url' => 'https://images.unsplash.com/photo-1611892440504-42a792e24d32?w=1200&q=80'],
                    ['media_type' => 'Image', 'media_url' => 'https://images.unsplash.com/photo-1540518614846-7eded433c457?w=1200&q=80'],
                ],
            ],
            [
                'title'               => 'Condo-Type Apartment in Lahug',
                'description'         => 'Condo-style apartment unit in Lahug, Cebu City. With air conditioning, hot and cold shower, fiber WiFi, and CCTV. Near Ayala Center.',
                'property_type'       => 'Apartment',
                'address'             => 'Lahug, Cebu City, Cebu',
                'latitude'            => 10.3280,
                'longitude'           => 123.8980,
                'verification_status' => 'Approved',
                'units' => [
                    ['unit_label' => 'Unit 1', 'rental_fee' => 15000, 'occupancy_limit' => 2, 'availability_status' => 'Available'],
                ],
                'media' => [
                    ['media_type' => 'Image', 'media_url' => 'https://images.unsplash.com/photo-1493809842364-78817add7ffb?w=1200&q=80'],
                    ['media_type' => 'Image', 'media_url' => 'https://images.unsplash.com/photo-1502672260266-1c1ef2d93688?w=1200&q=80'],
                    ['media_type' => 'Image', 'media_url' => 'https://images.unsplash.com/photo-1524758631624-e2822e304c36?w=1200&q=80'],
                ],
            ],
            [
                'title'               => 'Bedspace Near Cebu Doctors University',
                'description'         => 'Male bedspace accommodation near Cebu Doctors University. Suitable for medical students. With study table, locker, and 24-hour water supply.',
                'property_type'       => 'Bedspace',
                'address'             => 'Osmena Blvd, Cebu City, Cebu',
                'latitude'            => 10.3070,
                'longitude'           => 123.8930,
                'verification_status' => 'Approved',
                'units' => [
                    ['unit_label' => 'Bed 1', 'rental_fee' => 2800, 'occupancy_limit' => 1, 'availability_status' => 'Available'],
                    ['unit_label' => 'Bed 2', 'rental_fee' => 2800, 'occupancy_limit' => 1, 'availability_status' => 'Available'],
                    ['unit_label' => 'Bed 3', 'rental_fee' => 2500, 'occupancy_limit' => 1, 'availability_status' => 'Available'],
                    ['unit_label' => 'Bed 4', 'rental_fee' => 2500, 'occupancy_limit' => 1, 'availability_status' => 'Available'],
                ],
                'media' => [
                    ['media_type' => 'Image', 'media_url' => 'https://images.unsplash.com/photo-1555854877-bab0e564b8d5?w=1200&q=80'],
                    ['media_type' => 'Image', 'media_url' => 'https://images.unsplash.com/photo-1505691938895-1758d7feb511?w=1200&q=80'],
                ],
            ],

            // ───────────────────────── TALISAY CITY ─────────────────────────

            [
                'title'               => 'Quiet Room for Rent in Basak, Lapu-Lapu City',
                'description'         => 'Simple room close to the Mactan airport road. Easy jeepney access to Mactan Newtown and the Cebu-Mactan bridges. Shared kitchen and bathroom.',
                'property_type'       => 'Room',
                'address'             => 'Basak, Lapu-Lapu City, Cebu',
                'latitude'            => 10.2913,
                'longitude'           => 123.9629,
                'verification_status' => 'Approved',
                'units' => [
                    ['unit_label' => 'Unit 1', 'rental_fee' => 3500, 'occupancy_limit' => 1, 'availability_status' => 'Available'],
                ],
                'media' => [
                    ['media_type' => 'Image', 'media_url' => 'https://images.unsplash.com/photo-1536376072261-38c75010e6c9?w=1200&q=80'],
                    ['media_type' => 'Image', 'media_url' => 'https://images.unsplash.com/photo-1505691938895-1758d7feb511?w=1200&q=80'],
                ],
            ],
            [
                'title'               => 'Bedspace Near Mactan Airport, Pajo',
                'description'         => 'Budget bedspace in Pajo, a short ride from Mactan-Cebu International Airport. Fan room, shared CR, water included.',
                'property_type'       => 'Bedspace',
                'address'             => 'Pajo, Lapu-Lapu City, Cebu',
                'latitude'            => 10.3172,
                'longitude'           => 123.9722,
                'verification_status' => 'Approved',
                'units' => [
                    ['unit_label' => 'Bed A', 'rental_fee' => 2200, 'occupancy_limit' => 1, 'availability_status' => 'Available'],
                    ['unit_label' => 'Bed B', 'rental_fee' => 2200, 'occupancy_limit' => 1, 'availability_status' => 'Available'],
                ],
                'media' => [
                    ['media_type' => 'Image', 'media_url' => 'https://images.unsplash.com/photo-1522708323590-d24dbb6b0267?w=1200&q=80'],
                    ['media_type' => 'Image', 'media_url' => 'https://images.unsplash.com/photo-1560448204-603b3fc33ddc?w=1200&q=80'],
                ],
            ],
            [
                'title'               => '1-Bedroom Apartment in Poblacion, Consolacion',
                'description'         => 'Modern 1-bedroom unit near the Consolacion town center. Near groceries, fast food, and the highway to Cebu City.',
                'property_type'       => 'Apartment',
                'address'             => 'Poblacion, Consolacion, Cebu',
                'latitude'            => 10.3764,
                'longitude'           => 123.9577,
                'verification_status' => 'Approved',
                'units' => [
                    ['unit_label' => 'Unit 1', 'rental_fee' => 9500, 'occupancy_limit' => 2, 'availability_status' => 'Available'],
                ],
                'media' => [
                    ['media_type' => 'Image', 'media_url' => 'https://images.unsplash.com/photo-1502672260266-1c1ef2d93688?w=1200&q=80'],
                    ['media_type' => 'Image', 'media_url' => 'https://images.unsplash.com/photo-1554995207-c18c203602cb?w=1200&q=80'],
                ],
            ],
            [
                'title'               => 'Small Bungalow House in Catarman, Liloan',
                'description'         => 'Standalone bungalow in Liloan. Quiet, with a small yard and parking space for one vehicle.',
                'property_type'       => 'House',
                'address'             => 'Catarman, Liloan, Cebu',
                'latitude'            => 10.3986,
                'longitude'           => 123.9978,
                'verification_status' => 'Approved',
                'units' => [
                    ['unit_label' => 'Unit 1', 'rental_fee' => 14000, 'occupancy_limit' => 5, 'availability_status' => 'Available'],
                ],
                'media' => [
                    ['media_type' => 'Image', 'media_url' => 'https://images.unsplash.com/photo-1631049307264-da0ec9d70304?w=1200&q=80'],
                    ['media_type' => 'Image', 'media_url' => 'https://images.unsplash.com/photo-1585771724684-38269d6639fd?w=1200&q=80'],
                ],
            ],
            [
                'title'               => 'Furnished Room in Gabi, Cordova',
                'description'         => 'Fully furnished room with bed frame, cabinet, and study table. Located in a residential area in Gabi, Cordova.',
                'property_type'       => 'Room',
                'unit_profile'        => ['furnishing_status' => 'Furnished'],
                'address'             => 'Gabi, Cordova, Cebu',
                'latitude'            => 10.2564,
                'longitude'           => 123.9508,
                'verification_status' => 'Approved',
                'units' => [
                    ['unit_label' => 'Unit 1', 'rental_fee' => 4000, 'occupancy_limit' => 1, 'availability_status' => 'Available'],
                ],
                'media' => [
                    ['media_type' => 'Image', 'media_url' => 'https://images.unsplash.com/photo-1598928506311-c55ded91a20c?w=1200&q=80'],
                    ['media_type' => 'Image', 'media_url' => 'https://images.unsplash.com/photo-1556909114-f6e7ad7d3136?w=1200&q=80'],
                ],
            ],
            [
                'title'               => 'Coastal Bedspace in Poblacion, Compostela',
                'description'         => 'Bedspace close to the Compostela coastline. Shared kitchen, electric fans, near the public market.',
                'property_type'       => 'Bedspace',
                'address'             => 'Poblacion, Compostela, Cebu',
                'latitude'            => 10.4547,
                'longitude'           => 124.0186,
                'verification_status' => 'Approved',
                'units' => [
                    ['unit_label' => 'Unit 1', 'rental_fee' => 2300, 'occupancy_limit' => 6, 'availability_status' => 'Available'],
                ],
                'media' => [
                    ['media_type' => 'Image', 'media_url' => 'https://images.unsplash.com/photo-1583608205776-bfd35f0d9f83?w=1200&q=80'],
                    ['media_type' => 'Image', 'media_url' => 'https://images.unsplash.com/photo-1595526114035-0d45ed16cfbf?w=1200&q=80'],
                ],
            ],
            [
                'title'               => 'Family House for Rent in Sabang, Danao',
                'description'         => 'Spacious house in Danao City. 3 bedrooms, garden space, and covered carport.',
                'property_type'       => 'House',
                'address'             => 'Sabang, Danao City, Cebu',
                'latitude'            => 10.5208,
                'longitude'           => 124.0272,
                'verification_status' => 'Approved',
                'units' => [
                    ['unit_label' => 'Unit 1', 'rental_fee' => 16000, 'occupancy_limit' => 6, 'availability_status' => 'Available'],
                ],
                'media' => [
                    ['media_type' => 'Image', 'media_url' => 'https://images.unsplash.com/photo-1586105251261-72a756497a11?w=1200&q=80'],
                    ['media_type' => 'Image', 'media_url' => 'https://images.unsplash.com/photo-1568605114967-8130f3a36994?w=1200&q=80'],
                ],
            ],
            [
                'title'               => 'Studio Apartment Near Carcar City Hall',
                'description'         => 'Compact studio in the Carcar city proper, walking distance to City Hall and the public market.',
                'property_type'       => 'Apartment',
                'address'             => 'Poblacion, Carcar City, Cebu',
                'latitude'            => 10.1064,
                'longitude'           => 123.6408,
                'verification_status' => 'Approved',
                'units' => [
                    ['unit_label' => 'Unit 1', 'rental_fee' => 8500, 'occupancy_limit' => 2, 'availability_status' => 'Available'],
                ],
                'media' => [
                    ['media_type' => 'Image', 'media_url' => 'https://images.unsplash.com/photo-1570129477492-45c003edd2be?w=1200&q=80'],
                    ['media_type' => 'Image', 'media_url' => 'https://images.unsplash.com/photo-1512917774080-9991f1c4c750?w=1200&q=80'],
                ],
            ],
            [
                'title'               => 'Affordable Room in Poblacion, Toledo',
                'description'         => 'No-frills room for budget-conscious tenants in Toledo City. Shared comfort room, near the tricycle terminal.',
                'property_type'       => 'Room',
                'address'             => 'Poblacion, Toledo City, Cebu',
                'latitude'            => 10.3772,
                'longitude'           => 123.6386,
                'verification_status' => 'Approved',
                'units' => [
                    ['unit_label' => 'Unit 1', 'rental_fee' => 3200, 'occupancy_limit' => 1, 'availability_status' => 'Available'],
                ],
                'media' => [
                    ['media_type' => 'Image', 'media_url' => 'https://images.unsplash.com/photo-1611892440504-42a792e24d32?w=1200&q=80'],
                    ['media_type' => 'Image', 'media_url' => 'https://images.unsplash.com/photo-1540518614846-7eded433c457?w=1200&q=80'],
                ],
            ],
            [
                'title'               => 'Bedspace Near Balamban Public Market',
                'description'         => 'Bedspace right beside the Balamban town center. Convenient for factory workers and commuters.',
                'property_type'       => 'Bedspace',
                'address'             => 'Poblacion, Balamban, Cebu',
                'latitude'            => 10.5,
                'longitude'           => 123.7167,
                'verification_status' => 'Approved',
                'units' => [
                    ['unit_label' => 'Bed 1', 'rental_fee' => 2000, 'occupancy_limit' => 1, 'availability_status' => 'Available'],
                    ['unit_label' => 'Bed 2', 'rental_fee' => 2000, 'occupancy_limit' => 1, 'availability_status' => 'Available'],
                    ['unit_label' => 'Bed 3', 'rental_fee' => 1800, 'occupancy_limit' => 1, 'availability_status' => 'Available'],
                ],
                'media' => [
                    ['media_type' => 'Image', 'media_url' => 'https://images.unsplash.com/photo-1524758631624-e2822e304c36?w=1200&q=80'],
                    ['media_type' => 'Image', 'media_url' => 'https://images.unsplash.com/photo-1555854877-bab0e564b8d5?w=1200&q=80'],
                ],
            ],

            // ───────────────────────── MINGLANILLA ─────────────────────────

            [
                'title'               => 'Room for Rent in Poblacion, Argao',
                'description'         => 'Clean room in a residential compound in Argao, a short walk from the town plaza.',
                'property_type'       => 'Room',
                'address'             => 'Poblacion, Argao, Cebu',
                'latitude'            => 9.8828,
                'longitude'           => 123.6008,
                'verification_status' => 'Approved',
                'units' => [
                    ['unit_label' => 'Unit 1', 'rental_fee' => 3300, 'occupancy_limit' => 1, 'availability_status' => 'Available'],
                ],
                'media' => [
                    ['media_type' => 'Image', 'media_url' => 'https://images.unsplash.com/photo-1493809842364-78817add7ffb?w=1200&q=80'],
                    ['media_type' => 'Image', 'media_url' => 'https://images.unsplash.com/photo-1484154218962-a197022b5858?w=1200&q=80'],
                ],
            ],
            [
                'title'               => 'Quiet House in Basdiot, Moalboal',
                'description'         => 'Single-detached house in Moalboal, minutes from the beach and dive spots. 3 bedrooms, fenced lot, with carport.',
                'property_type'       => 'House',
                'address'             => 'Basdiot, Moalboal, Cebu',
                'latitude'            => 9.945,
                'longitude'           => 123.39,
                'verification_status' => 'Approved',
                'units' => [
                    ['unit_label' => 'Unit 1', 'rental_fee' => 13000, 'occupancy_limit' => 5, 'availability_status' => 'Available'],
                ],
                'media' => [
                    ['media_type' => 'Image', 'media_url' => 'https://images.unsplash.com/photo-1536376072261-38c75010e6c9?w=1200&q=80'],
                    ['media_type' => 'Image', 'media_url' => 'https://images.unsplash.com/photo-1505691938895-1758d7feb511?w=1200&q=80'],
                ],
            ],
            [
                'title'               => 'Budget Bedspace in Poblacion, Bogo City',
                'description'         => 'Budget bedspace in Bogo City. Basic amenities, shared kitchen.',
                'property_type'       => 'Bedspace',
                'address'             => 'Poblacion, Bogo City, Cebu',
                'latitude'            => 11.0475,
                'longitude'           => 124.0053,
                'verification_status' => 'Approved',
                'units' => [
                    ['unit_label' => 'Unit 1', 'rental_fee' => 2000, 'occupancy_limit' => 4, 'availability_status' => 'Available'],
                ],
                'media' => [
                    ['media_type' => 'Image', 'media_url' => 'https://images.unsplash.com/photo-1522708323590-d24dbb6b0267?w=1200&q=80'],
                    ['media_type' => 'Image', 'media_url' => 'https://images.unsplash.com/photo-1560448204-603b3fc33ddc?w=1200&q=80'],
                ],
            ],
            [
                'title'               => 'Apartment Unit Near San Fernando Town Proper',
                'description'         => 'Apartment unit close to the San Fernando town proper and public market. Good for small families or sharers.',
                'property_type'       => 'Apartment',
                'address'             => 'Poblacion, San Fernando, Cebu',
                'latitude'            => 10.1667,
                'longitude'           => 123.7,
                'verification_status' => 'Approved',
                'units' => [
                    ['unit_label' => 'Unit 1A', 'rental_fee' => 9000, 'occupancy_limit' => 2, 'availability_status' => 'Available'],
                    ['unit_label' => 'Unit 1B', 'rental_fee' => 8500, 'occupancy_limit' => 2, 'availability_status' => 'Available'],
                    ['unit_label' => 'Unit 2A', 'rental_fee' => 9500, 'occupancy_limit' => 3, 'availability_status' => 'Available'],
                ],
                'media' => [
                    ['media_type' => 'Image', 'media_url' => 'https://images.unsplash.com/photo-1502672260266-1c1ef2d93688?w=1200&q=80'],
                    ['media_type' => 'Image', 'media_url' => 'https://images.unsplash.com/photo-1554995207-c18c203602cb?w=1200&q=80'],
                ],
            ],
            [
                'title'               => 'Room for Rent in Poblacion, Bantayan',
                'description'         => 'Simple room in the Bantayan Island town center. Suitable for solo tenants working nearby.',
                'property_type'       => 'Room',
                'address'             => 'Poblacion, Bantayan, Cebu',
                'latitude'            => 11.169,
                'longitude'           => 123.722,
                'verification_status' => 'Approved',
                'units' => [
                    ['unit_label' => 'Unit 1', 'rental_fee' => 3000, 'occupancy_limit' => 1, 'availability_status' => 'Available'],
                ],
                'media' => [
                    ['media_type' => 'Image', 'media_url' => 'https://images.unsplash.com/photo-1631049307264-da0ec9d70304?w=1200&q=80'],
                    ['media_type' => 'Image', 'media_url' => 'https://images.unsplash.com/photo-1585771724684-38269d6639fd?w=1200&q=80'],
                ],
            ],
            [
                'title'               => 'Beachside Bedspace in Poblacion, Oslob',
                'description'         => 'Bedspace near the Oslob shoreline, a short tricycle ride from the national highway.',
                'property_type'       => 'Bedspace',
                'address'             => 'Poblacion, Oslob, Cebu',
                'latitude'            => 9.46,
                'longitude'           => 123.43,
                'verification_status' => 'Approved',
                'units' => [
                    ['unit_label' => 'Unit 1', 'rental_fee' => 2100, 'occupancy_limit' => 6, 'availability_status' => 'Available'],
                ],
                'media' => [
                    ['media_type' => 'Image', 'media_url' => 'https://images.unsplash.com/photo-1598928506311-c55ded91a20c?w=1200&q=80'],
                    ['media_type' => 'Image', 'media_url' => 'https://images.unsplash.com/photo-1556909114-f6e7ad7d3136?w=1200&q=80'],
                ],
            ],
            [
                'title'               => 'Town Center Apartment, Minglanilla Poblacion',
                'description'         => 'Apartment unit right in Minglanilla Poblacion, walking distance to the municipal hall, church, and terminal.',
                'property_type'       => 'Apartment',
                'address'             => 'Poblacion, Minglanilla, Cebu',
                'latitude'            => 10.2450,
                'longitude'           => 123.7950,
                'verification_status' => 'Approved',
                'units' => [
                    ['unit_label' => 'Unit 1', 'rental_fee' => 9800, 'occupancy_limit' => 2, 'availability_status' => 'Available'],
                ],
                'media' => [
                    ['media_type' => 'Image', 'media_url' => 'https://images.unsplash.com/photo-1583608205776-bfd35f0d9f83?w=1200&q=80'],
                    ['media_type' => 'Image', 'media_url' => 'https://images.unsplash.com/photo-1595526114035-0d45ed16cfbf?w=1200&q=80'],
                ],
            ],
            [
                'title'               => 'Seaside House for Rent in Tubod, Minglanilla',
                'description'         => 'Roomy house close to the Tubod shoreline. 3 bedrooms, dirty kitchen, and covered patio.',
                'property_type'       => 'House',
                'address'             => 'Tubod, Minglanilla, Cebu',
                'latitude'            => 10.2420,
                'longitude'           => 123.7890,
                'verification_status' => 'Approved',
                'units' => [
                    ['unit_label' => 'Unit 1', 'rental_fee' => 17000, 'occupancy_limit' => 6, 'availability_status' => 'Available'],
                ],
                'media' => [
                    ['media_type' => 'Image', 'media_url' => 'https://images.unsplash.com/photo-1586105251261-72a756497a11?w=1200&q=80'],
                    ['media_type' => 'Image', 'media_url' => 'https://images.unsplash.com/photo-1568605114967-8130f3a36994?w=1200&q=80'],
                ],
            ],
            [
                'title'               => 'Budget Room in Tulay, Minglanilla',
                'description'         => 'Simple, affordable room for rent in Tulay. Fan, single bed, shared bathroom.',
                'property_type'       => 'Room',
                'address'             => 'Tulay, Minglanilla, Cebu',
                'latitude'            => 10.2380,
                'longitude'           => 123.7820,
                'verification_status' => 'Approved',
                'units' => [
                    ['unit_label' => 'Unit 1', 'rental_fee' => 2800, 'occupancy_limit' => 1, 'availability_status' => 'Available'],
                ],
                'media' => [
                    ['media_type' => 'Image', 'media_url' => 'https://images.unsplash.com/photo-1570129477492-45c003edd2be?w=1200&q=80'],
                    ['media_type' => 'Image', 'media_url' => 'https://images.unsplash.com/photo-1512917774080-9991f1c4c750?w=1200&q=80'],
                ],
            ],
            [
                'title'               => 'Spacious House in Tunghaan, Minglanilla',
                'description'         => 'Large family house in Tunghaan with a wide yard, ideal for tenants wanting space outside the city.',
                'property_type'       => 'House',
                'address'             => 'Tunghaan, Minglanilla, Cebu',
                'latitude'            => 10.2530,
                'longitude'           => 123.7920,
                'verification_status' => 'Approved',
                'units' => [
                    ['unit_label' => 'Unit 1', 'rental_fee' => 15000, 'occupancy_limit' => 5, 'availability_status' => 'Available'],
                ],
                'media' => [
                    ['media_type' => 'Image', 'media_url' => 'https://images.unsplash.com/photo-1611892440504-42a792e24d32?w=1200&q=80'],
                    ['media_type' => 'Image', 'media_url' => 'https://images.unsplash.com/photo-1540518614846-7eded433c457?w=1200&q=80'],
                ],
            ],

            // ───────────────────────── NAGA CITY, CEBU ─────────────────────────

            [
                'title'               => 'Upland House for Rent in Alpaco, Naga City',
                'description'         => 'Detached house in the upland barangay of Alpaco, Naga City, Cebu. Quiet surroundings, good for families wanting distance from the highway.',
                'property_type'       => 'House',
                'address'             => 'Alpaco, Naga City, Cebu',
                'latitude'            => 10.2220,
                'longitude'           => 123.7380,
                'verification_status' => 'Approved',
                'units' => [
                    ['unit_label' => 'Unit 1', 'rental_fee' => 11000, 'occupancy_limit' => 5, 'availability_status' => 'Available'],
                ],
                'media' => [
                    ['media_type' => 'Image', 'media_url' => 'https://images.unsplash.com/photo-1524758631624-e2822e304c36?w=1200&q=80'],
                    ['media_type' => 'Image', 'media_url' => 'https://images.unsplash.com/photo-1555854877-bab0e564b8d5?w=1200&q=80'],
                ],
            ],
            [
                'title'               => 'Bedspace Near Bairan Industrial Zone, Naga City',
                'description'         => 'Affordable bedspace in Bairan, convenient for workers in the nearby industrial and manufacturing area.',
                'property_type'       => 'Bedspace',
                'address'             => 'Bairan, Naga City, Cebu',
                'latitude'            => 10.2310,
                'longitude'           => 123.7420,
                'verification_status' => 'Approved',
                'units' => [
                    ['unit_label' => 'Unit 1', 'rental_fee' => 1900, 'occupancy_limit' => 4, 'availability_status' => 'Available'],
                ],
                'media' => [
                    ['media_type' => 'Image', 'media_url' => 'https://images.unsplash.com/photo-1493809842364-78817add7ffb?w=1200&q=80'],
                    ['media_type' => 'Image', 'media_url' => 'https://images.unsplash.com/photo-1484154218962-a197022b5858?w=1200&q=80'],
                ],
            ],
            [
                'title'               => 'Room for Rent in Cabungahan, Naga City',
                'description'         => 'Single room with own entrance in Cabungahan, Naga City. Near the barangay hall and elementary school.',
                'property_type'       => 'Room',
                'address'             => 'Cabungahan, Naga City, Cebu',
                'latitude'            => 10.2300,
                'longitude'           => 123.7500,
                'verification_status' => 'Approved',
                'units' => [
                    ['unit_label' => 'Unit 1', 'rental_fee' => 2700, 'occupancy_limit' => 1, 'availability_status' => 'Available'],
                ],
                'media' => [
                    ['media_type' => 'Image', 'media_url' => 'https://images.unsplash.com/photo-1536376072261-38c75010e6c9?w=1200&q=80'],
                    ['media_type' => 'Image', 'media_url' => 'https://images.unsplash.com/photo-1505691938895-1758d7feb511?w=1200&q=80'],
                ],
            ],
            [
                'title'               => 'Apartment Unit in Colon, Naga City',
                'description'         => 'Small apartment unit in the Colon district of Naga City. Near the coastal road and public transport.',
                'property_type'       => 'Apartment',
                'address'             => 'Colon, Naga City, Cebu',
                'latitude'            => 10.2180,
                'longitude'           => 123.7580,
                'verification_status' => 'Approved',
                'units' => [
                    ['unit_label' => 'Unit 1', 'rental_fee' => 8000, 'occupancy_limit' => 2, 'availability_status' => 'Available'],
                ],
                'media' => [
                    ['media_type' => 'Image', 'media_url' => 'https://images.unsplash.com/photo-1522708323590-d24dbb6b0267?w=1200&q=80'],
                    ['media_type' => 'Image', 'media_url' => 'https://images.unsplash.com/photo-1560448204-603b3fc33ddc?w=1200&q=80'],
                ],
            ],
            [
                'title'               => 'Farm-Adjacent House in Inayagan, Naga City',
                'description'         => 'House for rent in the rural barangay of Inayagan, bordered by farmland. Peaceful setting, far from city noise.',
                'property_type'       => 'House',
                'address'             => 'Inayagan, Naga City, Cebu',
                'latitude'            => 10.2270,
                'longitude'           => 123.7660,
                'verification_status' => 'Approved',
                'units' => [
                    ['unit_label' => 'Unit 1', 'rental_fee' => 9500, 'occupancy_limit' => 6, 'availability_status' => 'Available'],
                ],
                'media' => [
                    ['media_type' => 'Image', 'media_url' => 'https://images.unsplash.com/photo-1502672260266-1c1ef2d93688?w=1200&q=80'],
                    ['media_type' => 'Image', 'media_url' => 'https://images.unsplash.com/photo-1554995207-c18c203602cb?w=1200&q=80'],
                ],
            ],
            [
                'title'               => 'Room Near Mainit Hot Spring Area, Naga City',
                'description'         => 'Room for rent in Mainit, known locally for its natural hot spring. Quiet barangay close to the coastline.',
                'property_type'       => 'Room',
                'address'             => 'Mainit, Naga City, Cebu',
                'latitude'            => 10.2220,
                'longitude'           => 123.7460,
                'verification_status' => 'Approved',
                'units' => [
                    ['unit_label' => 'Unit 1', 'rental_fee' => 3000, 'occupancy_limit' => 1, 'availability_status' => 'Available'],
                ],
                'media' => [
                    ['media_type' => 'Image', 'media_url' => 'https://images.unsplash.com/photo-1631049307264-da0ec9d70304?w=1200&q=80'],
                    ['media_type' => 'Image', 'media_url' => 'https://images.unsplash.com/photo-1585771724684-38269d6639fd?w=1200&q=80'],
                ],
            ],
            [
                'title'               => 'Affordable Bedspace in Pangdan, Naga City',
                'description'         => 'Budget bedspace accommodation in Pangdan. Basic amenities, shared kitchen, fan rooms only.',
                'property_type'       => 'Bedspace',
                'address'             => 'Pangdan, Naga City, Cebu',
                'latitude'            => 10.2310,
                'longitude'           => 123.7580,
                'verification_status' => 'Approved',
                'units' => [
                    ['unit_label' => 'Unit 1', 'rental_fee' => 1800, 'occupancy_limit' => 5, 'availability_status' => 'Available'],
                ],
                'media' => [
                    ['media_type' => 'Image', 'media_url' => 'https://images.unsplash.com/photo-1598928506311-c55ded91a20c?w=1200&q=80'],
                    ['media_type' => 'Image', 'media_url' => 'https://images.unsplash.com/photo-1556909114-f6e7ad7d3136?w=1200&q=80'],
                ],
            ],
            [
                'title'               => 'Town Proper Apartment, Naga City Poblacion',
                'description'         => 'Apartment unit in Naga City Poblacion, near the city hall, church, and public market.',
                'property_type'       => 'Apartment',
                'address'             => 'Poblacion, Naga City, Cebu',
                'latitude'            => 10.2080,
                'longitude'           => 123.7570,
                'verification_status' => 'Approved',
                'units' => [
                    ['unit_label' => 'Unit 1', 'rental_fee' => 8500, 'occupancy_limit' => 2, 'availability_status' => 'Available'],
                ],
                'media' => [
                    ['media_type' => 'Image', 'media_url' => 'https://images.unsplash.com/photo-1583608205776-bfd35f0d9f83?w=1200&q=80'],
                    ['media_type' => 'Image', 'media_url' => 'https://images.unsplash.com/photo-1595526114035-0d45ed16cfbf?w=1200&q=80'],
                ],
            ],
            [
                'title'               => 'Quiet Family House in Tagjaguimit, Naga City',
                'description'         => 'Family-sized house for rent in Tagjaguimit. 3 bedrooms, fenced lot, room for a small garden.',
                'property_type'       => 'House',
                'address'             => 'Tagjaguimit, Naga City, Cebu',
                'latitude'            => 10.2350,
                'longitude'           => 123.7350,
                'verification_status' => 'Approved',
                'units' => [
                    ['unit_label' => 'Unit 1', 'rental_fee' => 12000, 'occupancy_limit' => 5, 'availability_status' => 'Available'],
                ],
                'media' => [
                    ['media_type' => 'Image', 'media_url' => 'https://images.unsplash.com/photo-1586105251261-72a756497a11?w=1200&q=80'],
                    ['media_type' => 'Image', 'media_url' => 'https://images.unsplash.com/photo-1568605114967-8130f3a36994?w=1200&q=80'],
                ],
            ],
            [
                'title'               => 'Bedspace in Uling, Naga City',
                'description'         => 'Bedspace in Uling, a historically mining-adjacent barangay in Naga City. Basic but secure, shared facilities.',
                'property_type'       => 'Bedspace',
                'address'             => 'Uling, Naga City, Cebu',
                'latitude'            => 10.2450,
                'longitude'           => 123.7250,
                'verification_status' => 'Approved',
                'units' => [
                    ['unit_label' => 'Unit 1', 'rental_fee' => 1700, 'occupancy_limit' => 6, 'availability_status' => 'Available'],
                ],
                'media' => [
                    ['media_type' => 'Image', 'media_url' => 'https://images.unsplash.com/photo-1570129477492-45c003edd2be?w=1200&q=80'],
                    ['media_type' => 'Image', 'media_url' => 'https://images.unsplash.com/photo-1512917774080-9991f1c4c750?w=1200&q=80'],
                ],
            ],
        ];

        $captionPool = [
            'Sleeping area with natural light',
            'Freshly cleaned before turnover',
            'View from the doorway',
            'Storage and study corner',
            'Shared comfort room nearby',
            'Ventilated with ceiling fan',
        ];

        $unitAmenityIds = Amenity::forUnit()->pluck('amenity_id');
        $propertyAmenityIds = Amenity::forProperty()->pluck('amenity_id');

        $maxUnits = 30; // keep the seeded dataset small enough to browse
        $unitsCreated = 0;

        foreach ($properties as $data) {
            $mediaItems = $data['media'];
            $unitItems = $data['units'];
            unset($data['media'], $data['units']);

            // Cap total seeded units — skip properties that would overshoot
            if ($unitsCreated + count($unitItems) > $maxUnits) {
                continue;
            }

            // Pick 4–6 random rules from the pool matching this property type
            $pool = $rulesByType[$data['property_type']] ?? $roomRules;
            $data['house_rules'] = collect($pool)->shuffle()->take(rand(4, 6))->values()->all();

            // Every address here is "..., <LGU>, Cebu" — same shape parsed by
            // the add_locality_to_properties_table migration's backfill.
            $addressParts = array_map('trim', explode(',', $data['address']));
            $data['city_municipality'] = count($addressParts) >= 2
                ? $addressParts[count($addressParts) - 2]
                : 'Cebu City';

            $property = Property::create(array_merge($data, [
                'landlord_id' => $landlord->user_id,
            ]));

            // 3–6 random building-wide amenities on the property itself
            if ($propertyAmenityIds->isNotEmpty()) {
                $property->amenities()->attach(
                    $propertyAmenityIds->shuffle()->take(rand(3, min(6, $propertyAmenityIds->count())))->values()->all()
                );
            }

            // Create units with their own interior photos + amenities
            foreach ($unitItems as $unitData) {
                // Roughly 9 sqm a head, jittered. Every 5th unit is left null on
                // purpose so the "landlord never filled this in" path stays
                // visible in dev data instead of only existing in theory.
                $floorArea = $unitsCreated % 5 === 4
                    ? null
                    : round(($unitData['occupancy_limit'] ?? 2) * 9 + rand(-3, 8), 2);

                $unit = $property->units()->create(array_merge([
                    'floor_area_sqm' => $floorArea,
                ], $unitData, [
                    'verification_status' => $unitData['verification_status'] ?? 'Approved',
                ]));
                $unitsCreated++;

                // 3 interior photos per unit in unit_media (what the app reads)
                $unitPhotos = collect($unitImagePool)->shuffle()->take(3)->values();
                foreach ($unitPhotos as $i => $url) {
                    UnitMedia::create([
                        'unit_id'    => $unit->unit_id,
                        'media_type' => 'Image',
                        'media_url'  => $url,
                        'source'     => 'camera',
                        'caption'    => $i === 0 ? collect($captionPool)->random() : null,
                    ]);
                }

                // 3–6 random amenities per unit
                if ($unitAmenityIds->isNotEmpty()) {
                    $unit->amenities()->attach(
                        $unitAmenityIds->shuffle()->take(rand(3, min(6, $unitAmenityIds->count())))->values()->all()
                    );
                }
            }

            // Property-level photos (building exterior, common areas)
            foreach ($mediaItems as $media) {
                PropertyMedia::create([
                    'property_id' => $property->property_id,
                    'media_type'  => $media['media_type'],
                    'media_url'   => $media['media_url'],
                ]);
            }
        }
    }
}