<?php

namespace Database\Seeders;

use App\Models\Amenity;
use App\Models\Property;
use App\Models\PropertyMedia;
use App\Models\UnitMedia;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * 62 listings across 46 Cebu cities and towns, owned by 8 different landlords
 * (Maria Santos from UserSeeder plus the seven in LandlordSeeder), 123 units.
 *
 * Coordinates are the real barangay/town centres, so pins land where the
 * address says. Rent is priced to the local market: a bedspace in Talisay
 * costs less than a studio at IT Park. Everything is Approved + Published so
 * the landing page, area tiles, search and map have data for every area.
 *
 * Unit tuple: [label, monthly rent, occupancy, options]. Options override the
 * per-type defaults in unitDefaults(): type, beds, baths, sqm, bath, furn,
 * kitchen, floor, avail, deposit (months of rent), desc.
 *
 * Runs after AmenitySeeder and LandlordSeeder.
 */
class PropertySeeder extends Seeder
{
    /** Landlord key: 'maria' or the index into LandlordSeeder::LANDLORDS. */
    private const MARIA = 'maria';

    private const PROPERTY_PHOTOS = [
        'Bedspace' => [
            '1555854877-bab0e564b8d5', '1493809842364-78817add7ffb', '1484154218962-a197022b5858',
            '1595526114035-0d45ed16cfbf', '1586105251261-72a756497a11',
        ],
        'Boarding House' => [
            '1536376072261-38c75010e6c9', '1505691938895-1758d7feb511', '1631049307264-da0ec9d70304',
            '1585771724684-38269d6639fd', '1611892440504-42a792e24d32', '1540518614846-7eded433c457',
        ],
        'Apartment' => [
            '1522708323590-d24dbb6b0267', '1560448204-603b3fc33ddc', '1502672260266-1c1ef2d93688',
            '1554995207-c18c203602cb', '1598928506311-c55ded91a20c', '1583608205776-bfd35f0d9f83',
            '1524758631624-e2822e304c36',
        ],
        'Condominium' => [
            '1522708323590-d24dbb6b0267', '1502672260266-1c1ef2d93688', '1554995207-c18c203602cb',
            '1493809842364-78817add7ffb', '1524758631624-e2822e304c36',
        ],
        'House' => [
            '1568605114967-8130f3a36994', '1570129477492-45c003edd2be', '1512917774080-9991f1c4c750',
            '1564013799919-ab600027ffc6', '1600585154340-be6161a56a0c',
        ],
    ];

    private const UNIT_PHOTOS = [
        '1522771739844-6a9f6d5f14af', '1540518614846-7eded433c457', '1616594039964-ae9021a400a0',
        '1560185007-cde436f6a4d0', '1615874959474-d609969a20ed', '1585412727339-54e4bae3bbf9',
        '1505693416388-ac5ce068fe85', '1560448204-603b3fc33ddc', '1556909114-f6e7ad7d3136',
        '1594560913095-8cf34bab82ad', '1560185008-b033106af5c8', '1631049307264-da0ec9d70304',
    ];

    private const CAPTIONS = [
        'Sleeping area with natural light', 'Freshly cleaned before turnover', 'View from the doorway',
        'Storage and study corner', 'Comfort room', 'Ventilated with ceiling fan',
    ];

    private const RULES = [
        'Bedspace' => [
            'Quiet hours from 10:00 PM to 6:00 AM', 'No overnight visitors', 'No smoking inside the premises',
            'No cooking inside the sleeping area', 'Keep your bedspace clean and organized',
            'Curfew is 10:30 PM, please text the caretaker if you will be late', 'No pets allowed',
            'Clean the shared comfort room after use', 'Report maintenance issues to the caretaker right away',
        ],
        'Boarding House' => [
            'No smoking inside the room', 'No pets allowed', 'Quiet hours from 10:00 PM to 6:00 AM',
            'Visitors allowed until 9:00 PM only', 'Electricity is billed by sub-meter reading',
            'One month advance and one month deposit', 'No alterations to the room without landlord approval',
            'Dispose of garbage in the designated bins', 'Keep shared areas clean after use',
        ],
        'Apartment' => [
            'No smoking inside the unit', 'No pets unless approved in writing', 'Quiet hours from 10:00 PM to 6:00 AM',
            'No subletting or unregistered occupants', 'Parking is limited to assigned slots',
            'Segregate garbage and dispose on collection days', 'Overnight guests must be registered at the guard house',
            'No changes to walls or fixtures without approval', 'Report leaks or electrical faults immediately',
        ],
        'Condominium' => [
            'No smoking inside the unit', 'No pets unless allowed by the condominium corporation', 'Quiet hours from 10:00 PM to 6:00 AM',
            'Overnight guests must be registered at the lobby', 'Amenity deck use follows the building schedule',
            'No subletting or Airbnb-style short stays', 'Association dues are handled by the owner unless stated',
            'Move-in and move-out only on approved hours', 'Report leaks or electrical faults immediately',
        ],
        'House' => [
            'No smoking inside the house', 'Pets allowed with prior approval', 'Tenant keeps the yard clean and trimmed',
            'No subletting', 'Report plumbing or electrical issues immediately',
            'Water and electricity are billed to the tenant', 'Gate is locked by 10:00 PM',
            'No structural changes without written consent', 'Two months deposit, one month advance',
        ],
    ];

    /** Amenity names each type is likely to have, so a bedspace never gets an elevator. */
    private const AMENITIES = [
        'Bedspace' => [
            'property' => ['CCTV', 'Gated Entrance', 'Water Dispenser', 'Shared Kitchen', 'Laundry Area', 'Near Public Transport', 'Curfew', 'Near Market / Grocery'],
            'unit'     => ['Electric Fan', 'Wardrobe / Cabinet', 'Bed Included', 'Shared Bathroom', 'Study Table'],
        ],
        'Boarding House' => [
            'property' => ['CCTV', 'Gated Entrance', 'Shared Kitchen', 'Motorcycle Parking', 'Near Public Transport', 'Near Market / Grocery', 'Laundry Area'],
            'unit'     => ['Electric Fan', 'Wi-Fi', 'Wardrobe / Cabinet', 'Bed Included', 'Submeter (Electricity)', 'Hot Shower'],
        ],
        'Apartment' => [
            'property' => ['CCTV', '24/7 Security', 'Gated Entrance', 'Parking Space', 'Elevator', 'Near Public Transport', 'Rooftop Access'],
            'unit'     => ['Air Conditioning', 'Wi-Fi', 'Private Bathroom', 'Private Kitchen', 'Hot Shower', 'Refrigerator', 'Balcony', 'Bed Included'],
        ],
        'Condominium' => [
            'property' => ['24/7 Security', 'CCTV', 'Elevator', 'Parking Space', 'Rooftop Access', 'Near Public Transport', 'Gated Entrance'],
            'unit'     => ['Air Conditioning', 'Wi-Fi', 'Private Bathroom', 'Private Kitchen', 'Hot Shower', 'Refrigerator', 'Balcony', 'Bed Included'],
        ],
        'House' => [
            'property' => ['Parking Space', 'Gated Entrance', 'Backup Generator', 'Pet Friendly', 'Near Market / Grocery', 'Near Public Transport'],
            'unit'     => ['Private Kitchen', 'Private Bathroom', 'Hot Shower', 'Washing Machine', 'Balcony', 'Electric Fan'],
        ],
    ];

    public function run(): void
    {
        $landlords = [self::MARIA => User::where('email', 'landlord@abangananhub.com')->firstOrFail()];
        foreach (LandlordSeeder::LANDLORDS as $i => $l) {
            $landlords[$i] = User::where('email', $l['email'])->firstOrFail();
        }

        foreach ($this->catalogue() as $n => $row) {
            [$owner, $title, $type, $barangay, $city, $lat, $lng, $arrangement, $utilities, $description, $units] = $row;
            // The catalogue predates the split: gender values become the occupancy preference; couples/family have no equivalent.
            [$arrangement, $occupancy] = match ($arrangement) {
                'Female only' => ['Shared', 'Women Only'],
                'Male only' => ['Shared', 'Men Only'],
                'Couples allowed', 'Family-friendly' => ['Private', 'No Preference'],
                default => [$arrangement, 'No Preference'],
            };

            $property = Property::updateOrCreate(
                ['landlord_id' => $landlords[$owner]->user_id, 'title' => $title],
                [
                    'description'                  => $description,
                    'house_rules'                  => \App\Support\PropertyPolicies::compileRules(
                        collect(\App\Support\PropertyPolicies::HOUSE_RULES)->shuffle()->take(rand(2, 4))->all(),
                        collect(self::RULES[$type])->random()
                    ),
                    'property_type'                => $type,
                    'living_arrangement'           => $arrangement,
                    'occupancy_preference'         => $occupancy,
                    'water_included'               => $utilities[0],
                    'electricity_included'         => $utilities[1],
                    'internet_included'            => $utilities[2],
                    'association_fees_included'    => $utilities[3],
                    'utilities_separately_metered' => $utilities[4],
                    'address'                      => "{$barangay}, {$city}, Cebu",
                    'city_municipality'            => $city,
                    'barangay'                     => $barangay,
                    'latitude'                     => $lat,
                    'longitude'                    => $lng,
                    'verification_status'          => 'Approved',
                    'publication_status'           => 'Published',
                ]
            );

            if ($property->wasRecentlyCreated) {
                $this->attachProperty($property, $type, $n);
                foreach ($units as $u => $unit) {
                    $this->createUnit($property, $type, $unit, $n * 7 + $u);
                }
            }
        }
    }

    private function attachProperty(Property $property, string $type, int $n): void
    {
        $ids = Amenity::forProperty()->whereIn('amenity_name', self::AMENITIES[$type]['property'])->pluck('amenity_id');
        $property->amenities()->attach($ids->shuffle()->take(rand(3, 5))->all());

        $pool = self::PROPERTY_PHOTOS[$type];
        foreach (range(0, 2) as $k) {
            PropertyMedia::create([
                'property_id' => $property->property_id,
                'media_type'  => 'Image',
                'media_url'   => 'https://images.unsplash.com/photo-'.$pool[($n + $k) % count($pool)].'?w=1200&q=80',
            ]);
        }
    }

    private function createUnit(Property $property, string $type, array $tuple, int $seed): void
    {
        [$label, $fee, $occupancy, $opts] = $tuple + [3 => []];
        $d = $this->unitDefaults($type, $occupancy, $opts);

        $unit = $property->units()->create([
            'unit_label'          => $label,
            'unit_type'           => $d['type'],
            'floor'               => $d['floor'],
            'bedrooms'            => $d['beds'],
            'bathrooms'           => $d['baths'],
            'floor_area_sqm'      => $d['sqm'],
            'furnishing_status'   => $d['furn'],
            'is_furnished'        => $d['furn'] === 'Furnished',
            'description'         => $d['desc'],
            'rental_fee'          => $fee,
            'security_deposit'    => $fee * ($opts['deposit'] ?? ($type === 'House' ? 2 : 1)),
            'occupancy_limit'     => $occupancy,
            'availability_status' => $opts['avail'] ?? 'Available',
            'verification_status' => 'Approved',
        ]);

        foreach (range(0, 2) as $k) {
            UnitMedia::create([
                'unit_id'    => $unit->unit_id,
                'media_type' => 'Image',
                'media_url'  => 'https://images.unsplash.com/photo-'.self::UNIT_PHOTOS[($seed + $k * 5) % count(self::UNIT_PHOTOS)].'?w=800&q=80',
                'source'     => 'camera',
                'caption'    => $k === 0 ? self::CAPTIONS[$seed % count(self::CAPTIONS)] : null,
            ]);
        }

        $ids = Amenity::forUnit()->whereIn('amenity_name', self::AMENITIES[$type]['unit'])->pluck('amenity_id');
        $unit->amenities()->attach($ids->shuffle()->take(rand(3, 5))->all());
    }

    private function unitDefaults(string $type, int $occupancy, array $o): array
    {
        $base = match ($type) {
            'Bedspace' => [
                'type' => 'Bedspace', 'beds' => null, 'baths' => 1, 'sqm' => null, 'bath' => 'Shared bathroom',
                'furn' => 'Furnished', 'kitchen' => 'Shared kitchen', 'floor' => 'Ground floor',
                'desc' => 'Own bed and locker in a shared room. Sheets not included.',
            ],
            'Boarding House' => [
                'type' => 'Private room', 'beds' => 1, 'baths' => 1, 'sqm' => 12 + $occupancy * 3, 'bath' => 'Shared bathroom',
                'furn' => 'Semi-furnished', 'kitchen' => 'Shared kitchen', 'floor' => 'Ground floor',
                'desc' => 'Private room with its own door and lock. Bed frame and cabinet provided.',
            ],
            'Apartment' => [
                'type' => 'Studio', 'beds' => 0, 'baths' => 1, 'sqm' => 22 + $occupancy * 4, 'bath' => 'Private bathroom',
                'furn' => 'Semi-furnished', 'kitchen' => 'Private kitchen', 'floor' => '2nd floor',
                'desc' => 'Self-contained unit with its own comfort room and kitchenette.',
            ],
            'Condominium' => [
                'type' => 'Studio', 'beds' => 0, 'baths' => 1, 'sqm' => 24 + $occupancy * 4, 'bath' => 'Private bathroom',
                'furn' => 'Furnished', 'kitchen' => 'Private kitchen', 'floor' => '12th floor',
                'desc' => 'Condominium unit with its own comfort room and kitchenette. Building amenities follow the association rules.',
            ],
            default => [
                'type' => 'Whole house', 'beds' => 3, 'baths' => 2, 'sqm' => 70 + $occupancy * 6, 'bath' => 'Private bathroom',
                'furn' => 'Unfurnished', 'kitchen' => 'Private kitchen', 'floor' => 'Ground floor',
                'desc' => 'Entire house with lot, sold as one rental. Tenant keeps the yard.',
            ],
        };

        return array_merge($base, $o);
    }

    /**
     * [landlord, title, type, barangay, city, lat, lng, arrangement,
     *  [water, electricity, internet, assoc fees, sub-metered], description, units]
     */
    private function catalogue(): array
    {
        $M = self::MARIA;
        $none = [false, false, false, false, true];

        return [
            // ─── Cebu City ───────────────────────────────────
            [$M, 'Labangon Bedspace near Cebu Technological', 'Bedspace', 'Labangon', 'Cebu City', 10.2990, 123.8850, 'Mixed',
                [true, false, true, false, true],
                'Boarding house on a quiet side street off Labangon Road, five minutes by jeepney to Colon. Caretaker lives on the ground floor. Water and Wi-Fi are covered, electricity is split by sub-meter.',
                [['Bed A', 2500, 1], ['Bed B', 2500, 1], ['Bed C', 2200, 1, ['floor' => '2nd floor']], ['Bed D', 2200, 1, ['floor' => '2nd floor']]]],

            [$M, 'Private Rooms along P. del Rosario, Colon', 'Boarding House', 'Colon', 'Cebu City', 10.2981, 123.9005, 'Private',
                [false, false, true, false, true],
                'Rooms in an old family house a short walk from USC Downtown and Carbon Market. Wi-Fi included. Popular with students and jeepney-commuting workers.',
                [['Room 1', 4500, 1], ['Room 2', 4000, 1, ['sqm' => 10]], ['Room 3', 4500, 1, ['floor' => '2nd floor']]]],

            [$M, 'Female Bedspace, Punta Princesa', 'Bedspace', 'Punta Princesa', 'Cebu City', 10.2965, 123.8790, 'Female only',
                [true, false, false, false, true],
                'Ladies-only boarding house with a live-in caretaker. Curfew 10:00 PM. Ten minutes to Cebu South Bus Terminal and near a public market.',
                [['Bed 1', 2000, 1], ['Bed 2', 2000, 1], ['Bed 3', 2000, 1]]],

            [$M, 'Rooms near SM Seaside, Mambaling', 'Boarding House', 'Mambaling', 'Cebu City', 10.2835, 123.8755, 'Private',
                [false, false, false, false, true],
                'Ventilated rooms at the back of a two-storey house, ten minutes from SM Seaside and the SRP. Electricity billed per sub-meter, water is flat.',
                [['Room A', 4000, 1], ['Room B', 3800, 1, ['avail' => 'Maintenance']]]],

            [6, 'IT Park Studios, Apas', 'Condominium', 'Apas', 'Cebu City', 10.3297, 123.9056, 'Private',
                [true, false, true, true, false],
                'Fully furnished studios a five-minute walk from Cebu IT Park. Aircon, fiber Wi-Fi and 24/7 guard. Many tenants are BPO and IT workers on night shifts.',
                [['Unit 101', 12000, 2, ['furn' => 'Furnished', 'floor' => '1st floor']], ['Unit 102', 12000, 2, ['furn' => 'Furnished', 'floor' => '1st floor']], ['Unit 201', 14000, 2, ['furn' => 'Furnished', 'floor' => '2nd floor', 'sqm' => 32]], ['Unit 202', 14000, 2, ['furn' => 'Furnished', 'floor' => '2nd floor', 'sqm' => 32]]]],

            [6, 'Two-Bedroom Apartments in Banilad', 'Apartment', 'Banilad', 'Cebu City', 10.3402, 123.9128, 'Family-friendly',
                [false, false, false, true, true],
                'Two-bedroom units in a quiet subdivision off Banilad Road. Washing area, one parking slot per unit, and walking distance to Gaisano Country Mall.',
                [['Unit A', 18000, 4, ['type' => '2BR', 'beds' => 2, 'sqm' => 58, 'deposit' => 2]], ['Unit B', 16000, 3, ['type' => '2BR', 'beds' => 2, 'sqm' => 50, 'deposit' => 2]]]],

            [6, 'Condo-Type Unit, Lahug', 'Condominium', 'Lahug', 'Cebu City', 10.3283, 123.8982, 'Couples allowed',
                [true, false, true, true, false],
                'Condo-style one-bedroom near Ayala Center and JY Square. Hot shower, aircon, CCTV in the hallway.',
                [['Unit 1', 15000, 2, ['type' => '1BR', 'beds' => 1, 'furn' => 'Furnished', 'sqm' => 30, 'deposit' => 2]]]],

            [$M, 'Bedspace near Cebu Doctors, Osmeña Blvd', 'Bedspace', 'Kamputhaw', 'Cebu City', 10.3112, 123.9068, 'Male only',
                [true, false, true, false, true],
                'Male bedspace for medical and nursing students. Study table per bed and 24-hour water. A short jeepney ride to Cebu Doctors and Chong Hua.',
                [['Bed 1', 2800, 1], ['Bed 2', 2800, 1], ['Bed 3', 2800, 1], ['Bed 4', 2800, 1]]],

            [6, 'Mabolo Garden Apartment', 'Apartment', 'Mabolo', 'Cebu City', 10.3180, 123.9170, 'Mixed',
                [false, false, false, false, true],
                'Ground-floor unit with a small garden, two minutes from the Mabolo church and a busy market street.',
                [['Unit 1', 9500, 2, ['type' => '1BR', 'beds' => 1, 'sqm' => 28]], ['Unit 2', 9500, 2, ['type' => '1BR', 'beds' => 1, 'sqm' => 28]]]],

            [$M, 'Talamban Student Rooms', 'Boarding House', 'Talamban', 'Cebu City', 10.3525, 123.9120, 'Private',
                [true, false, true, false, true],
                'Rooms for USC Talamban students and UP Cebu staff. Wi-Fi included, shared kitchen, rooftop drying area.',
                [['Room 1', 4200, 1], ['Room 2', 4200, 1], ['Room 3', 4800, 2, ['bath' => 'Private bathroom', 'sqm' => 18]]]],

            // ─── Mandaue / Consolacion / Liloan / Cordova ─────
            [0, 'Affordable Room in Bakilid, Mandaue', 'Boarding House', 'Bakilid', 'Mandaue City', 10.3342, 123.9303, 'Private',
                [true, false, false, false, true],
                'Budget room with a ceiling fan and cabinet. Near the wet market and jeepney routes to Colon and Ayala.',
                [['Unit 1', 3000, 1]]],

            [0, 'Subangdaku Boarding House', 'Boarding House', 'Subangdaku', 'Mandaue City', 10.3405, 123.9410, 'Mixed',
                [true, false, true, false, true],
                'Eight-door boarding house near Mandaue City Hall and the Cebu-Mandaue bridge. Popular with factory and warehouse staff.',
                [['Room 1', 3500, 1], ['Room 2', 3500, 1], ['Room 3', 3800, 2, ['sqm' => 16]]]],

            [0, 'Maguikay Duplex Apartments', 'Apartment', 'Maguikay', 'Mandaue City', 10.3392, 123.9318, 'Family-friendly',
                [false, false, false, false, true],
                'Duplex units with a small carport. Ten minutes to Parkmall and the Mandaue Reclamation Area.',
                [['Unit A', 11000, 3, ['type' => '2BR', 'beds' => 2, 'sqm' => 42]], ['Unit B', 11000, 3, ['type' => '2BR', 'beds' => 2, 'sqm' => 42]]]],

            [0, 'Nangka Room Rental, Consolacion', 'Boarding House', 'Nangka', 'Consolacion', 10.3790, 123.9580, 'Private',
                [true, false, false, false, true],
                'Rooms beside the national highway, ten minutes from SM Consolacion. Jeepneys to Mandaue pass every few minutes.',
                [['Room A', 3200, 1], ['Room B', 3200, 1]]],

            [0, 'Liloan Poblacion Apartment', 'Apartment', 'Poblacion', 'Liloan', 10.3993, 123.9985, 'Family-friendly',
                [false, false, false, false, true],
                'One-bedroom apartment near the Liloan municipal hall and church. Landlord lives next door.',
                [['Unit 1', 7500, 3, ['type' => '1BR', 'beds' => 1, 'sqm' => 32]]]],

            [1, 'Gabi Rooms, Cordova', 'Boarding House', 'Gabi', 'Cordova', 10.2585, 123.9510, 'Private',
                [true, false, false, false, true],
                'Rooms in Gabi, ten minutes from the Cordova pier and Mactan Airport. Quiet street, tricycle terminal at the corner.',
                [['Room 1', 3500, 1], ['Room 2', 3500, 1]]],

            // ─── Lapu-Lapu / Mactan ──────────────────────────
            [1, 'Basak Bedspace, Lapu-Lapu', 'Bedspace', 'Basak', 'Lapu-Lapu City', 10.2935, 123.9605, 'Mixed',
                [true, false, false, false, true],
                'Bedspace for MEPZ factory workers. Ten minutes to the Mactan Export Processing Zone by tricycle.',
                [['Bed 1', 2200, 1], ['Bed 2', 2200, 1], ['Bed 3', 2200, 1]]],

            [1, 'Pajo Apartments near Gaisano Mactan', 'Apartment', 'Pajo', 'Lapu-Lapu City', 10.3180, 123.9530, 'Couples allowed',
                [false, false, true, false, true],
                'Studios and one-bedrooms near Gaisano Mall of Mactan. Wi-Fi included, own meters.',
                [['Unit 1', 8500, 2], ['Unit 2', 9500, 2, ['type' => '1BR', 'beds' => 1, 'sqm' => 30]]]],

            [1, 'Maribago Staff Rooms', 'Boarding House', 'Maribago', 'Lapu-Lapu City', 10.2685, 123.9780, 'Private',
                [true, false, false, false, true],
                'Rooms for resort and dive-shop staff along the Maribago strip. Walking distance to the beach road.',
                [['Room 1', 4000, 1], ['Room 2', 4000, 1], ['Room 3', 4500, 2, ['sqm' => 18]]]],

            // ─── Talisay / Minglanilla / Naga / Carcar ────────
            [2, 'Family House in San Isidro, Talisay', 'House', 'San Isidro', 'Talisay City', 10.2560, 123.8435, 'Family-friendly',
                [false, false, false, false, true],
                'Three-bedroom house in a peaceful subdivision. Carport, dirty kitchen and a small garden.',
                [['Whole House', 22000, 6, ['sqm' => 96]]]],

            [2, 'Linao Boarding House, Talisay', 'Bedspace', 'Linao', 'Talisay City', 10.2662, 123.8505, 'Mixed',
                [true, false, true, false, true],
                'Bedspace near the Talisay City Hall. Ten minutes to Cebu City by jeepney.',
                [['Bed A', 1900, 1], ['Bed B', 1900, 1], ['Bed C', 1900, 1]]],

            [2, 'Tunghaan Rooms, Minglanilla', 'Boarding House', 'Tunghaan', 'Minglanilla', 10.2400, 123.7975, 'Private',
                [true, false, false, false, true],
                'Rooms near the Minglanilla Sports Complex and the SRP-South route. Quiet hillside barangay.',
                [['Room 1', 3000, 1], ['Room 2', 3000, 1], ['Room 3', 3200, 2, ['sqm' => 16]]]],

            [2, 'Tubod Apartment, Minglanilla', 'Apartment', 'Tubod', 'Minglanilla', 10.2465, 123.7990, 'Family-friendly',
                [false, false, false, false, true],
                'Two-bedroom apartment beside the highway. Near the Minglanilla town proper and Puregold.',
                [['Unit 1', 9000, 4, ['type' => '2BR', 'beds' => 2, 'sqm' => 45]]]],

            [2, 'Colon Bedspace, Naga City', 'Bedspace', 'Colon', 'Naga City', 10.2130, 123.7585, 'Mixed',
                [true, false, false, false, true],
                'Basic bedspace close to the Naga public market. Workers at the Naga power and cement plants stay here.',
                [['Bed 1', 1800, 1], ['Bed 2', 1800, 1], ['Bed 3', 1800, 1], ['Bed 4', 1700, 1]]],

            [2, 'Poblacion Apartment, Naga City', 'Apartment', 'Poblacion', 'Naga City', 10.2080, 123.7570, 'Family-friendly',
                [false, false, false, false, true],
                'Apartment near the Naga City Hall, church and plaza.',
                [['Unit 1', 8500, 3, ['type' => '1BR', 'beds' => 1, 'sqm' => 34]]]],

            [2, 'Poblacion Rooms, Carcar City', 'Boarding House', 'Poblacion', 'Carcar City', 10.1062, 123.6412, 'Private',
                [true, false, false, false, true],
                'Rooms a short walk from the Carcar Rotunda and the famous public market. Frequent buses to Cebu City and Argao.',
                [['Room 1', 3000, 1], ['Room 2', 3000, 1]]],

            // ─── North Cebu ──────────────────────────────────
            [3, 'Sabang Rooms, Danao City', 'Boarding House', 'Sabang', 'Danao City', 10.5215, 124.0250, 'Private',
                [true, false, false, false, true],
                'Rooms near the Danao port and public market. Ten minutes to the Danao city proper.',
                [['Room 1', 3000, 1], ['Room 2', 3000, 1], ['Room 3', 3500, 2, ['sqm' => 16]]]],

            [3, 'Compostela Family House', 'House', 'Poblacion', 'Compostela', 10.4535, 124.0030, 'Family-friendly',
                [false, false, false, false, true],
                'Two-bedroom house with a fenced yard, a few minutes from the town hall and the beach.',
                [['Whole House', 9500, 5, ['beds' => 2, 'baths' => 1, 'sqm' => 62]]]],

            [3, 'Bogo Poblacion Bedspace', 'Bedspace', 'Poblacion', 'Bogo City', 11.0503, 124.0055, 'Mixed',
                [true, false, false, false, true],
                'Bedspace for teachers and hospital staff in Bogo City. Walking distance to the public market.',
                [['Bed 1', 1600, 1], ['Bed 2', 1600, 1], ['Bed 3', 1600, 1]]],

            [3, 'Poblacion Rooms, Bantayan Island', 'Boarding House', 'Poblacion', 'Bantayan', 11.1690, 123.7235, 'Private',
                [true, false, false, false, true],
                'Rooms in Bantayan town for teachers and resort staff. Five minutes to the Santa Fe ferry terminal by habal-habal.',
                [['Room 1', 3500, 1], ['Room 2', 3500, 1]]],

            [3, 'San Remigio Beachside Room', 'Boarding House', 'Poblacion', 'San Remigio', 11.0745, 123.9430, 'Private',
                [true, false, false, false, true],
                'Simple room a short ride from the Hagnaya port. Suitable for people who commute to Bantayan.',
                [['Unit 1', 3000, 1]]],

            // ─── West Cebu ───────────────────────────────────
            [4, 'Toledo City Boarding House', 'Boarding House', 'Poblacion', 'Toledo City', 10.3775, 123.6385, 'Private',
                [true, false, false, false, true],
                'Rooms near the Toledo public market and port. Popular with workers from the nearby power and mining sites.',
                [['Room 1', 3500, 1], ['Room 2', 3500, 1], ['Room 3', 3800, 2, ['sqm' => 16]]]],

            [4, 'Balamban Family Apartment', 'Apartment', 'Poblacion', 'Balamban', 10.4675, 123.7115, 'Family-friendly',
                [false, false, false, false, true],
                'Apartment near the Balamban plaza. A few minutes from the shipyards and the town market.',
                [['Unit 1', 6500, 3, ['type' => '1BR', 'beds' => 1, 'sqm' => 30]]]],

            [4, 'Pinamungajan Bedspace', 'Bedspace', 'Poblacion', 'Pinamungajan', 10.2735, 123.5860, 'Mixed',
                [true, false, false, false, true],
                'Bedspace near the Pinamungajan town proper. Suitable for power-plant and construction workers.',
                [['Bed 1', 1500, 1], ['Bed 2', 1500, 1]]],

            // ─── South Cebu ──────────────────────────────────
            [5, 'Argao Poblacion Rooms', 'Boarding House', 'Poblacion', 'Argao', 9.8795, 123.6060, 'Private',
                [true, false, false, false, true],
                'Rooms a short walk from the Argao church, plaza and market.',
                [['Room 1', 3200, 1], ['Room 2', 3200, 1]]],

            [5, 'Moalboal Long-Stay Rooms', 'Boarding House', 'Basdiot', 'Moalboal', 9.9480, 123.3945, 'Private',
                [true, false, true, false, true],
                'Rooms for dive staff and remote workers, five minutes from Panagsama beach. Wi-Fi is enough for video calls.',
                [['Room 1', 5500, 1, ['bath' => 'Private bathroom']], ['Room 2', 5500, 1, ['bath' => 'Private bathroom']]]],

            [5, 'Badian Beach Cottage House', 'House', 'Zaragosa', 'Badian', 9.8690, 123.3925, 'Couples allowed',
                [false, false, true, false, true],
                'One-bedroom cottage a walk from the beach and the Kawasan Falls canyon road.',
                [['Cottage 1', 12000, 3, ['beds' => 1, 'baths' => 1, 'sqm' => 42, 'furn' => 'Semi-furnished']]]],

            [5, 'Oslob Poblacion Room', 'Boarding House', 'Poblacion', 'Oslob', 9.4602, 123.4295, 'Private',
                [true, false, false, false, true],
                'Room a short walk from the Oslob church. Frequent buses to Cebu City.',
                [['Unit 1', 3000, 1]]],

            [5, 'Dalaguete Rooms near Public Market', 'Boarding House', 'Poblacion', 'Dalaguete', 9.7625, 123.5365, 'Private',
                [true, false, false, false, true],
                'Rooms in a quiet street, five minutes from the Dalaguete market and the sea.',
                [['Room 1', 2800, 1], ['Room 2', 2800, 1]]],

            // ─── More towns: east coast, Camotes, the west and the far south ───
            [5, 'Sibonga Rooms near the Poblacion', 'Boarding House', 'Poblacion', 'Sibonga', 10.0085, 123.5985, 'Private',
                [true, false, false, false, true],
                'Rooms a few minutes from the Sibonga church and the Argao-bound bus stop. Quiet, with a family-run sari-sari store next door.',
                [['Room 1', 2800, 1], ['Room 2', 2800, 1]]],

            [4, 'Barili Town Proper Apartment', 'Apartment', 'Poblacion', 'Barili', 10.1155, 123.5175, 'Family-friendly',
                [false, false, false, false, true],
                'One-bedroom apartment near the Barili town hall and the public market. Convenient for teachers and municipal staff.',
                [['Unit 1', 5500, 3, ['type' => '1BR', 'beds' => 1, 'sqm' => 30]]]],

            [4, 'Dumanjug Bedspace near Highschool', 'Bedspace', 'Poblacion', 'Dumanjug', 10.0510, 123.4375, 'Mixed',
                [true, false, false, false, true],
                'Bedspace beside the Dumanjug national highschool. Popular with senior students who commute from the mountain barangays.',
                [['Bed 1', 1400, 1], ['Bed 2', 1400, 1], ['Bed 3', 1400, 1]]],

            [4, 'Aloguinsan Seaside Room', 'Boarding House', 'Poblacion', 'Aloguinsan', 10.2275, 123.5475, 'Private',
                [true, false, false, false, true],
                'Simple room near the Aloguinsan plaza and a short walk to the sea. Habal-habal to Bojo river takes ten minutes.',
                [['Unit 1', 2800, 1]]],

            [5, 'Alcantara Rooms by the Highway', 'Boarding House', 'Poblacion', 'Alcantara', 9.9690, 123.4045, 'Private',
                [true, false, false, false, true],
                'Rooms along the coastal highway between Moalboal and Ronda. Buses stop in front of the gate.',
                [['Room 1', 2600, 1], ['Room 2', 2600, 1]]],

            [5, 'Ronda Family House', 'House', 'Poblacion', 'Ronda', 9.9735, 123.4130, 'Family-friendly',
                [false, false, false, false, true],
                'Two-bedroom house with a fenced yard, five minutes from the Ronda town hall.',
                [['Whole House', 7500, 5, ['beds' => 2, 'baths' => 1, 'sqm' => 55]]]],

            [5, 'Ginatilan Bedspace for Teachers', 'Bedspace', 'Poblacion', 'Ginatilan', 9.5980, 123.3690, 'Mixed',
                [true, false, false, false, true],
                'Bedspace for public school teachers and rural health staff assigned to Ginatilan.',
                [['Bed A', 1500, 1], ['Bed B', 1500, 1]]],

            [5, 'Samboan Room near the Port', 'Boarding House', 'Poblacion', 'Samboan', 9.5255, 123.3145, 'Private',
                [true, false, false, false, true],
                'Room a short walk from the Samboan pier and the Bato ferry. Good for ferry staff and dive resort workers.',
                [['Unit 1', 3000, 1]]],

            [5, 'Santander Beach House', 'House', 'Liloan', 'Santander', 9.4205, 123.3395, 'Couples allowed',
                [false, false, false, false, true],
                'One-bedroom house near the Santander wharf, where the ferry to Dumaguete leaves. Fan-cooled, own kitchen.',
                [['Cottage 1', 6500, 3, ['beds' => 1, 'baths' => 1, 'sqm' => 38, 'furn' => 'Semi-furnished']]]],

            [5, 'Boljoon Heritage Town Room', 'Boarding House', 'Poblacion', 'Boljoon', 9.6335, 123.4470, 'Private',
                [true, false, false, false, true],
                'Room a few steps from the old Boljoon church. Quiet street with a sea breeze in the evening.',
                [['Room 1', 2800, 1], ['Room 2', 2800, 1]]],

            [5, 'Alcoy Coastal Rooms', 'Boarding House', 'Poblacion', 'Alcoy', 9.7000, 123.5010, 'Private',
                [true, false, false, false, true],
                'Rooms in Alcoy for tourism and fishing-port workers, five minutes from the plaza.',
                [['Unit 1', 2700, 1]]],

            [3, 'Carmen Poblacion Apartment', 'Apartment', 'Poblacion', 'Carmen', 10.5860, 124.0290, 'Family-friendly',
                [false, false, false, false, true],
                'One-bedroom unit near the Carmen town center. Jeepneys to Danao and Catmon pass by all day.',
                [['Unit 1', 5000, 3, ['type' => '1BR', 'beds' => 1, 'sqm' => 28]]]],

            [3, 'Catmon Bedspace', 'Bedspace', 'Poblacion', 'Catmon', 10.7150, 124.0190, 'Mixed',
                [true, false, false, false, true],
                'Bedspace for teachers and health workers in Catmon. Steps away from the plaza.',
                [['Bed 1', 1400, 1], ['Bed 2', 1400, 1]]],

            [3, 'Sogod Rooms near the Market', 'Boarding House', 'Poblacion', 'Sogod', 10.7430, 124.0080, 'Private',
                [true, false, false, false, true],
                'Rooms in Sogod town near the market and the vans to Cebu City.',
                [['Room 1', 2800, 1], ['Room 2', 2800, 1]]],

            [3, 'Tabogon Seaside Room', 'Boarding House', 'Poblacion', 'Tabogon', 10.9500, 124.0130, 'Private',
                [true, false, false, false, true],
                'Quiet room in Tabogon, walking distance to the town hall and the coast.',
                [['Unit 1', 2600, 1]]],

            [3, 'Borbon Poblacion Bedspace', 'Bedspace', 'Poblacion', 'Borbon', 10.8420, 124.0210, 'Mixed',
                [true, false, false, false, true],
                'Bedspace near the Borbon plaza for teachers and local government staff.',
                [['Bed 1', 1400, 1], ['Bed 2', 1400, 1]]],

            [3, 'Tuburan Rooms near the Town Hall', 'Boarding House', 'Poblacion', 'Tuburan', 10.7290, 123.8250, 'Private',
                [true, false, false, false, true],
                'Rooms in the Tuburan town proper on the west coast. Buses to Cebu City via Toledo and to Bogo.',
                [['Room 1', 2800, 1], ['Room 2', 2800, 1]]],

            [3, 'Daanbantayan Room near Malapascua Port', 'Boarding House', 'Poblacion', 'Daanbantayan', 11.2530, 124.0290, 'Private',
                [true, false, true, false, true],
                'Room ten minutes from Maya port, where the boat to Malapascua leaves. Dive staff and tourism workers stay here.',
                [['Room 1', 3500, 1], ['Room 2', 3500, 1]]],

            [3, 'Medellin Family House', 'House', 'Poblacion', 'Medellin', 11.1265, 123.9615, 'Family-friendly',
                [false, false, false, false, true],
                'Two-bedroom house in Medellin near the sugar mill road. Fenced lot, room to park a tricycle.',
                [['Whole House', 6500, 5, ['beds' => 2, 'baths' => 1, 'sqm' => 52]]]],

            [3, 'Madridejos Room near Bantayan Port', 'Boarding House', 'Poblacion', 'Madridejos', 11.2595, 123.7275, 'Private',
                [true, false, false, false, true],
                'Room in Madridejos, the northernmost town of Bantayan Island. Steps from the pier and the market.',
                [['Unit 1', 3200, 1]]],

            [3, 'Santa Fe Beach Bedspace', 'Bedspace', 'Poblacion', 'Santa Fe', 11.1620, 123.8000, 'Mixed',
                [true, false, false, false, true],
                'Bedspace near the Santa Fe port for resort workers and boatmen. Beach is a few minutes away.',
                [['Bed 1', 1800, 1], ['Bed 2', 1800, 1], ['Bed 3', 1800, 1]]],

            [3, 'Poro Camotes Island Room', 'Boarding House', 'Poblacion', 'Poro', 10.6265, 124.4185, 'Private',
                [true, false, false, false, true],
                'Room on Camotes Island, near the Poro town hall and the port at Consuelo.',
                [['Unit 1', 3000, 1]]],

            [3, 'San Francisco Camotes Bedspace', 'Bedspace', 'Poblacion', 'San Francisco', 10.6480, 124.3800, 'Mixed',
                [true, false, false, false, true],
                'Bedspace in the main town of the Camotes Islands, beside the public market.',
                [['Bed 1', 1700, 1], ['Bed 2', 1700, 1]]],
        ];
    }
}
