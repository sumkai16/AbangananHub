<?php

namespace Database\Seeders;

use App\Models\Amenity;
use App\Models\Property;
use App\Models\PropertyMedia;
use App\Models\PropertyUnit;
use App\Models\UnitMedia;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * The AI-search side of the capstone-defense world: every suggestion chip on the landing page and every demo search
 * in docs/defense-script.md returns at least three EXACT matches, so the results page shows a full, clean list (no
 * "closest options" fallback) on stage. About 39 approved listings spread over the seven business landlords, so
 * "places by this business" and "near me" have something to show too.
 *
 * Runs after the base seeders and the other Defense seeders. Only ADDS; idempotent (found by landlord and title);
 * deterministic (no randomness); photos are copied from an already-seeded listing, never created here.
 *
 * Each entry of SEARCHES is: the sentence to type => the listings that answer it.
 */
class DefenseSearchSeeder extends Seeder
{
    /** Landlord (by business name) => user id is looked up at run time. */
    private const OWNERS = [
        'alcoseba' => 'Alcoseba Rentals',
        'ouano'    => 'Ouano Family Properties',
        'tudtud'   => 'Tudtud Boarding Houses',
        'pepito'   => 'Pepito Homes North Cebu',
        'yap'      => 'Yap Western Cebu Rentals',
        'gonzaga'  => 'Gonzaga South Cebu Stays',
        'lozada'   => 'Lozada Urban Living',
    ];

    /**
     * The sentences the demo types. Shown on the landing page as suggestions (resources/views/components/ai-search-box.blade.php)
     * and listed in docs/defense-script.md.
     */
    public const SEARCHES = [
        'Dorm near IT Park, max ₱18k',
        'Bedspace para sa babae, may aircon',
        'Apartment sa Mandaue ubos 4k',
        'Bedspace near Lahug, aircon, under 3k',
        'Room near USC Talamban, Wi-Fi, under ₱5,000. I have a cat.',
        'Kwarto duol sa CIT-U, ubos sa 3k',
        'Bedspace for men near Colon, under 2k',
        'Boarding house sa Lapu-Lapu, walang curfew',
        'House sa Talisay na may parking, 10k pababa',
    ];

    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('DefenseSearchSeeder is demo data and never runs in production.');
        }

        $model = Property::whereHas('media')->with(['media', 'units.media'])->orderBy('property_id')->first();
        $modelUnit = $model?->units->first(fn ($u) => $u->media->isNotEmpty());
        $amenityIds = Amenity::pluck('amenity_id', 'amenity_name');

        foreach ($this->catalogue() as $row) {
            $owner = $this->owner($row['owner']);
            if (! $owner) {
                continue; // the base seed did not create this landlord on this machine
            }

            $property = Property::firstOrCreate(
                ['landlord_id' => $owner->user_id, 'title' => $row['title']],
                [
                    'description'                  => $row['description'],
                    'house_rules'                  => $row['rules'],
                    'property_type'                => $row['type'],
                    'living_arrangement'           => 'Private',
                    'occupancy_preference'         => $row['for'] ?? 'No Preference',
                    'water_included'               => true,
                    'electricity_included'         => false,
                    'internet_included'            => in_array('Wi-Fi', $row['amenities'], true),
                    'association_fees_included'    => false,
                    'utilities_separately_metered' => true,
                    'address'                      => "{$row['barangay']}, {$row['city']}, Cebu",
                    'city_municipality'            => $row['city'],
                    'barangay'                     => $row['barangay'],
                    'latitude'                     => $row['lat'],
                    'longitude'                    => $row['lng'],
                    'verification_status'          => 'Approved',
                    'publication_status'           => 'Published',
                ]
            );

            $ids = collect($row['amenities'])->map(fn ($name) => $amenityIds[$name] ?? null)->filter()->values()->all();

            if ($property->wasRecentlyCreated && $model) {
                foreach ($model->media as $m) {
                    PropertyMedia::create(['property_id' => $property->property_id, 'media_type' => $m->media_type, 'media_url' => $m->media_url]);
                }
            }
            $property->amenities()->syncWithoutDetaching($ids);

            foreach (range(1, $row['units']) as $n) {
                $unit = PropertyUnit::firstOrCreate(
                    ['property_id' => $property->property_id, 'unit_label' => ($row['type'] === 'Bedspace' ? 'Bed ' : 'Unit ').$n],
                    [
                        'floor'               => $n % 2 === 1 ? '1st floor' : '2nd floor',
                        'bedrooms'            => $row['bedrooms'] ?? 1,
                        'bathrooms'           => 1,
                        'floor_area_sqm'      => $row['type'] === 'Bedspace' ? 8 : 22,
                        'furnishing_status'   => $row['furnishing'] ?? 'Semi-furnished',
                        'description'         => 'Clean, ready to move in, with steady water and a landlord who replies the same day.',
                        'rental_fee'          => $row['rent'],
                        'security_deposit'    => $row['deposit'] ?? $row['rent'],
                        'occupancy_limit'     => $row['occupants'] ?? ($row['type'] === 'Bedspace' ? 1 : 2),
                        'availability_status' => 'Available',
                        'verification_status' => 'Approved',
                    ]
                );

                $unit->amenities()->syncWithoutDetaching($ids);

                if ($unit->wasRecentlyCreated && $modelUnit) {
                    foreach ($modelUnit->media as $m) {
                        UnitMedia::create(['unit_id' => $unit->unit_id, 'media_type' => $m->media_type, 'media_url' => $m->media_url, 'source' => $m->source, 'caption' => $m->caption]);
                    }
                }
            }
        }
    }

    private function owner(string $key): ?User
    {
        return User::whereHas('rentalBusiness', fn ($q) => $q->where('business_name', self::OWNERS[$key]))->first();
    }

    /**
     * One row per listing. `for` is Men Only / Women Only; `rules` are the restricting house rules (a "pets allowed"
     * search skips a listing that carries "No Pets", a "no curfew" search skips "Curfew").
     *
     * @return list<array<string, mixed>>
     */
    private function catalogue(): array
    {
        $quiet = ['Quiet Hours'];
        $cats = ['Quiet Hours']; // carries no "No Pets": a pets search accepts it

        return [
            // Dorm near IT Park, max 18k
            ['owner' => 'lozada', 'title' => 'IT Park Residence Dormitory', 'type' => 'Dormitory', 'barangay' => 'Apas', 'city' => 'Cebu City', 'lat' => 10.3301, 'lng' => 123.9068, 'rent' => 9500, 'units' => 3, 'amenities' => ['Air Conditioning', 'Wi-Fi', '24/7 Security', 'Study Table'], 'rules' => $quiet, 'description' => 'Co-ed dormitory five minutes on foot from Cebu IT Park, for call-center and BPO staff. Aircon rooms, fiber Wi-Fi, guard at the gate.'],
            ['owner' => 'lozada', 'title' => 'Apas Pods Dormitory', 'type' => 'Dormitory', 'barangay' => 'Apas', 'city' => 'Cebu City', 'lat' => 10.3318, 'lng' => 123.9040, 'rent' => 6800, 'units' => 2, 'amenities' => ['Air Conditioning', 'Wi-Fi', 'Laundry Area'], 'rules' => $quiet, 'description' => 'Shared-room dormitory with pod beds, a laundry area and a common kitchen. Walking distance to the IT Park shuttle.'],
            ['owner' => 'alcoseba', 'title' => 'Lahug Executive Dormitory', 'type' => 'Dormitory', 'barangay' => 'Lahug', 'city' => 'Cebu City', 'lat' => 10.3322, 'lng' => 123.9024, 'rent' => 14500, 'units' => 2, 'amenities' => ['Air Conditioning', 'Wi-Fi', 'Private Bathroom', 'Refrigerator'], 'rules' => $quiet, 'furnishing' => 'Furnished', 'description' => 'Private-bath dormitory rooms for working professionals, a short ride from IT Park and Ayala.'],

            // Bedspace para sa babae, may aircon
            ['owner' => 'tudtud', 'title' => 'Ladies Bedspace with Aircon, Lahug', 'type' => 'Bedspace', 'barangay' => 'Lahug', 'city' => 'Cebu City', 'lat' => 10.3335, 'lng' => 123.9005, 'rent' => 2800, 'units' => 4, 'for' => 'Women Only', 'amenities' => ['Air Conditioning', 'Wi-Fi', 'Wardrobe / Cabinet'], 'rules' => $quiet, 'description' => 'Women-only bedspace with aircon, a locker for each guest and a house mother on site.'],
            ['owner' => 'tudtud', 'title' => 'Girls Bedspace Near USC Talamban', 'type' => 'Bedspace', 'barangay' => 'Talamban', 'city' => 'Cebu City', 'lat' => 10.3528, 'lng' => 123.9118, 'rent' => 2500, 'units' => 4, 'for' => 'Women Only', 'amenities' => ['Air Conditioning', 'Wi-Fi', 'Study Table'], 'rules' => $quiet, 'description' => 'Aircon bedspace for female students, two minutes from the USC Talamban gate.'],
            ['owner' => 'gonzaga', 'title' => 'Women Only Bedspace, Colon', 'type' => 'Bedspace', 'barangay' => 'Sambag I', 'city' => 'Cebu City', 'lat' => 10.2961, 'lng' => 123.8996, 'rent' => 2300, 'units' => 3, 'for' => 'Women Only', 'amenities' => ['Air Conditioning', 'Hot Shower'], 'rules' => $quiet, 'description' => 'Aircon bedspace for working women downtown. Jeepneys to every part of the city at the corner.'],

            // Apartment sa Mandaue ubos 4k
            ['owner' => 'ouano', 'title' => 'Budget Apartment, Subangdaku Mandaue', 'type' => 'Apartment', 'barangay' => 'Subangdaku', 'city' => 'Mandaue City', 'lat' => 10.3395, 'lng' => 123.9288, 'rent' => 3500, 'units' => 3, 'amenities' => ['Private Bathroom', 'Electric Fan'], 'rules' => $quiet, 'description' => 'Small one-room apartment units with their own comfort room, ideal for one or two people.'],
            ['owner' => 'ouano', 'title' => 'Canduman Starter Apartment, Mandaue', 'type' => 'Apartment', 'barangay' => 'Canduman', 'city' => 'Mandaue City', 'lat' => 10.3567, 'lng' => 123.9421, 'rent' => 3800, 'units' => 2, 'amenities' => ['Private Bathroom', 'Water Dispenser'], 'rules' => $quiet, 'description' => 'Starter apartments near the Canduman market, with a private comfort room and a kitchen counter.'],
            ['owner' => 'pepito', 'title' => 'Paknaan Studio Apartment, Mandaue', 'type' => 'Apartment', 'barangay' => 'Paknaan', 'city' => 'Mandaue City', 'lat' => 10.3262, 'lng' => 123.9475, 'rent' => 3900, 'units' => 2, 'amenities' => ['Private Bathroom', 'Parking Space'], 'rules' => $quiet, 'description' => 'Studio-type apartments with parking beside the house, five minutes from the Mandaue-Mactan bridge.'],

            // Bedspace near Lahug, aircon, under 3k
            ['owner' => 'tudtud', 'title' => 'Lahug Aircon Bedspace for Students', 'type' => 'Bedspace', 'barangay' => 'Lahug', 'city' => 'Cebu City', 'lat' => 10.3341, 'lng' => 123.8998, 'rent' => 2600, 'units' => 4, 'amenities' => ['Air Conditioning', 'Wi-Fi'], 'rules' => $quiet, 'description' => 'Aircon bedspace for students and young workers, a short walk to UP Cebu and JY Square.'],
            ['owner' => 'alcoseba', 'title' => 'JY Square Bedspace, Lahug', 'type' => 'Bedspace', 'barangay' => 'Lahug', 'city' => 'Cebu City', 'lat' => 10.3277, 'lng' => 123.9031, 'rent' => 2900, 'units' => 3, 'amenities' => ['Air Conditioning', 'Wi-Fi', 'Laundry Area'], 'rules' => $quiet, 'description' => 'Bedspace beside JY Square with aircon and a laundry area on the roof.'],

            // Room near USC Talamban, Wi-Fi, under 5k, with a cat
            ['owner' => 'gonzaga', 'title' => 'Pet-Friendly Room Near USC Talamban', 'type' => 'Boarding House', 'barangay' => 'Talamban', 'city' => 'Cebu City', 'lat' => 10.3535, 'lng' => 123.9109, 'rent' => 4200, 'units' => 3, 'amenities' => ['Wi-Fi', 'Pet Friendly', 'Private Bathroom'], 'rules' => $cats, 'description' => 'Rooms for students that allow cats and small dogs. Fiber Wi-Fi, and the landlord lives next door.'],
            ['owner' => 'gonzaga', 'title' => 'Talamban Student Rooms with Wi-Fi', 'type' => 'Boarding House', 'barangay' => 'Talamban', 'city' => 'Cebu City', 'lat' => 10.3517, 'lng' => 123.9131, 'rent' => 4800, 'units' => 3, 'amenities' => ['Wi-Fi', 'Pet Friendly', 'Study Table'], 'rules' => $cats, 'description' => 'Quiet rooms with a study table, a few minutes from USC Talamban. Pets are welcome.'],
            ['owner' => 'tudtud', 'title' => 'Cat-Friendly Boarding House, Talamban', 'type' => 'Boarding House', 'barangay' => 'Talamban', 'city' => 'Cebu City', 'lat' => 10.3549, 'lng' => 123.9094, 'rent' => 3900, 'units' => 2, 'amenities' => ['Wi-Fi', 'Pet Friendly'], 'rules' => $cats, 'description' => 'Small boarding house run by a cat owner. Wi-Fi included.'],

            // Kwarto duol sa CIT-U, ubos sa 3k
            ['owner' => 'yap', 'title' => 'Student Rooms by CIT-U, Labangon', 'type' => 'Boarding House', 'barangay' => 'Labangon', 'city' => 'Cebu City', 'lat' => 10.2957, 'lng' => 123.8761, 'rent' => 2700, 'units' => 3, 'amenities' => ['Electric Fan', 'Wi-Fi'], 'rules' => $quiet, 'description' => 'Simple rooms for students, a ten-minute walk from CIT-U.'],
            ['owner' => 'yap', 'title' => 'Room for Rent Near CIT-U, Punta Princesa', 'type' => 'Boarding House', 'barangay' => 'Punta Princesa', 'city' => 'Cebu City', 'lat' => 10.2931, 'lng' => 123.8828, 'rent' => 2900, 'units' => 2, 'amenities' => ['Electric Fan', 'Shared Kitchen'], 'rules' => $quiet, 'description' => 'Rooms with a shared kitchen. Jeepney 04 to CIT-U passes the gate.'],
            ['owner' => 'yap', 'title' => 'Budget Room Near CIT-U, Basak San Nicolas', 'type' => 'Boarding House', 'barangay' => 'Basak San Nicolas', 'city' => 'Cebu City', 'lat' => 10.2903, 'lng' => 123.8742, 'rent' => 2500, 'units' => 3, 'amenities' => ['Electric Fan', 'Wi-Fi'], 'rules' => $quiet, 'description' => 'The cheapest rooms near CIT-U, with Wi-Fi in the common area.'],

            // Bedspace for men near Colon, under 2k
            ['owner' => 'gonzaga', 'title' => 'Men Only Bedspace Near Colon', 'type' => 'Bedspace', 'barangay' => 'Sambag I', 'city' => 'Cebu City', 'lat' => 10.2949, 'lng' => 123.9012, 'rent' => 1800, 'units' => 4, 'for' => 'Men Only', 'amenities' => ['Electric Fan', 'Shared Bathroom'], 'rules' => $quiet, 'description' => 'Men-only bedspace a block from Colon, for jobseekers and working men.'],
            ['owner' => 'gonzaga', 'title' => 'Colon Lalaki Bedspace', 'type' => 'Bedspace', 'barangay' => 'Sambag II', 'city' => 'Cebu City', 'lat' => 10.2973, 'lng' => 123.8985, 'rent' => 1500, 'units' => 4, 'for' => 'Men Only', 'amenities' => ['Electric Fan', 'Shared Bathroom'], 'rules' => $quiet, 'description' => 'The lowest-priced bedspace downtown, for men only.'],
            ['owner' => 'alcoseba', 'title' => 'Carbon Area Bedspace for Men', 'type' => 'Bedspace', 'barangay' => 'Ermita', 'city' => 'Cebu City', 'lat' => 10.2938, 'lng' => 123.9025, 'rent' => 1900, 'units' => 3, 'for' => 'Men Only', 'amenities' => ['Electric Fan', 'Hot Shower'], 'rules' => $quiet, 'description' => 'Bedspace near Carbon Market, walking distance to Colon.'],

            // Boarding house sa Lapu-Lapu, walang curfew
            ['owner' => 'pepito', 'title' => 'Lapu-Lapu Boarding House, No Curfew', 'type' => 'Boarding House', 'barangay' => 'Pajo', 'city' => 'Lapu-Lapu City', 'lat' => 10.3115, 'lng' => 123.9622, 'rent' => 3800, 'units' => 3, 'amenities' => ['Wi-Fi', 'Visitors Allowed'], 'rules' => $quiet, 'description' => 'Boarding house with its own gate key for every guest, so no curfew. Near the Mactan airport road.'],
            ['owner' => 'pepito', 'title' => 'Basak Lapu-Lapu Rooms, Walang Curfew', 'type' => 'Boarding House', 'barangay' => 'Basak', 'city' => 'Lapu-Lapu City', 'lat' => 10.2918, 'lng' => 123.9587, 'rent' => 3500, 'units' => 3, 'amenities' => ['Wi-Fi', 'Electric Fan'], 'rules' => $quiet, 'description' => 'Rooms for factory and airport workers on night shifts. No curfew.'],
            ['owner' => 'pepito', 'title' => 'Maribago Shared Rooms, Lapu-Lapu', 'type' => 'Boarding House', 'barangay' => 'Maribago', 'city' => 'Lapu-Lapu City', 'lat' => 10.2893, 'lng' => 123.9792, 'rent' => 4100, 'units' => 2, 'amenities' => ['Wi-Fi', 'Laundry Area'], 'rules' => $quiet, 'description' => 'Rooms near the resorts, with a laundry area. Come and go any hour.'],

            // House sa Talisay na may parking, 10k pababa
            ['owner' => 'ouano', 'title' => 'Talisay Family House with Garage', 'type' => 'House', 'barangay' => 'Tabunok', 'city' => 'Talisay City', 'lat' => 10.2561, 'lng' => 123.8479, 'rent' => 9000, 'units' => 1, 'bedrooms' => 2, 'amenities' => ['Parking Space', 'Private Kitchen', 'Private Bathroom'], 'rules' => $quiet, 'description' => 'Two-bedroom house with a garage for one car, in a quiet Tabunok street.'],
            ['owner' => 'ouano', 'title' => 'Bungalow with Parking, Bulacao Talisay', 'type' => 'House', 'barangay' => 'Bulacao', 'city' => 'Talisay City', 'lat' => 10.2634, 'lng' => 123.8527, 'rent' => 8500, 'units' => 1, 'bedrooms' => 2, 'amenities' => ['Parking Space', 'Private Kitchen'], 'rules' => $quiet, 'description' => 'Small bungalow with a front yard for parking and a fenced back yard.'],
            // Taglish / Bisaya / mixed demo sentences (docs/defense-script.md): women-only near CIT-U, men's dorms near UC Main and CTU,
            // a wifi + aircon room near IT Park, pet-friendly boarding houses in Talisay, Mandaue apartments with parking.
            ['owner' => 'yap', 'title' => 'Ladies Bedspace with Wi-Fi by CIT-U', 'type' => 'Bedspace', 'barangay' => 'Labangon', 'city' => 'Cebu City', 'lat' => 10.2961, 'lng' => 123.8790, 'rent' => 2400, 'units' => 4, 'for' => 'Women Only', 'amenities' => ['Air Conditioning', 'Wi-Fi'], 'rules' => $quiet, 'description' => 'Women-only bedspace for CIT-U students, with aircon and Wi-Fi.'],
            ['owner' => 'yap', 'title' => 'Girls Dorm Bedspace, Punta Princesa', 'type' => 'Bedspace', 'barangay' => 'Punta Princesa', 'city' => 'Cebu City', 'lat' => 10.2947, 'lng' => 123.8831, 'rent' => 2700, 'units' => 3, 'for' => 'Women Only', 'amenities' => ['Air Conditioning', 'Wi-Fi', 'Wardrobe / Cabinet'], 'rules' => $quiet, 'description' => 'Aircon bedspace for women, ten minutes from CIT-U by jeepney.'],
            ['owner' => 'gonzaga', 'title' => 'Mens Dormitory Near UC Main', 'type' => 'Dormitory', 'barangay' => 'Sambag I', 'city' => 'Cebu City', 'lat' => 10.2996, 'lng' => 123.8991, 'rent' => 3200, 'units' => 3, 'for' => 'Men Only', 'amenities' => ['Wi-Fi', 'Electric Fan', 'Shared Bathroom'], 'rules' => $quiet, 'description' => 'Men-only dormitory a short walk from UC Main, for students and workers.'],
            ['owner' => 'gonzaga', 'title' => 'Boys Dorm, Sanciangko Cebu City', 'type' => 'Dormitory', 'barangay' => 'Sambag II', 'city' => 'Cebu City', 'lat' => 10.3001, 'lng' => 123.8957, 'rent' => 3400, 'units' => 3, 'for' => 'Men Only', 'amenities' => ['Wi-Fi', 'Electric Fan'], 'rules' => $quiet, 'description' => 'Boys dormitory between UC Main and CTU. Shared rooms, Wi-Fi in every room.'],
            ['owner' => 'alcoseba', 'title' => 'CTU Area Dorm for Men', 'type' => 'Dormitory', 'barangay' => 'Sambag I', 'city' => 'Cebu City', 'lat' => 10.3033, 'lng' => 123.8931, 'rent' => 2800, 'units' => 4, 'for' => 'Men Only', 'amenities' => ['Electric Fan', 'Shared Bathroom'], 'rules' => $quiet, 'description' => 'Low-priced dormitory for men beside CTU Main.'],
            ['owner' => 'alcoseba', 'title' => 'Fuente Men Dormitory', 'type' => 'Dormitory', 'barangay' => 'Capitol Site', 'city' => 'Cebu City', 'lat' => 10.3068, 'lng' => 123.8912, 'rent' => 2950, 'units' => 3, 'for' => 'Men Only', 'amenities' => ['Wi-Fi', 'Electric Fan'], 'rules' => $quiet, 'description' => 'Dormitory for men near CTU and Fuente.'],
            ['owner' => 'gonzaga', 'title' => 'Budget Ladies Bedspace, Colon', 'type' => 'Bedspace', 'barangay' => 'Ermita', 'city' => 'Cebu City', 'lat' => 10.2957, 'lng' => 123.9009, 'rent' => 1900, 'units' => 3, 'for' => 'Women Only', 'amenities' => ['Electric Fan', 'Hot Shower'], 'rules' => $quiet, 'description' => 'The lowest-priced women-only bedspace downtown, a few steps from Colon.'],
            ['owner' => 'gonzaga', 'title' => 'Babae Bedspace Near Carbon', 'type' => 'Bedspace', 'barangay' => 'Ermita', 'city' => 'Cebu City', 'lat' => 10.2942, 'lng' => 123.9019, 'rent' => 1800, 'units' => 3, 'for' => 'Women Only', 'amenities' => ['Electric Fan', 'Wi-Fi'], 'rules' => $quiet, 'description' => 'Bedspace for women who work around Carbon and Colon.'],
            ['owner' => 'lozada', 'title' => 'IT Park Rooms with Wi-Fi and Aircon', 'type' => 'Boarding House', 'barangay' => 'Apas', 'city' => 'Cebu City', 'lat' => 10.3309, 'lng' => 123.9047, 'rent' => 5500, 'units' => 3, 'amenities' => ['Air Conditioning', 'Wi-Fi', 'Private Bathroom'], 'rules' => $quiet, 'description' => 'Private rooms with aircon and fiber Wi-Fi, three minutes from Cebu IT Park.'],
            ['owner' => 'lozada', 'title' => 'Apas Aircon Rooms for Rent', 'type' => 'Boarding House', 'barangay' => 'Apas', 'city' => 'Cebu City', 'lat' => 10.3276, 'lng' => 123.9072, 'rent' => 5200, 'units' => 2, 'amenities' => ['Air Conditioning', 'Wi-Fi'], 'rules' => $quiet, 'description' => 'Aircon rooms with Wi-Fi for call-center workers.'],
            ['owner' => 'ouano', 'title' => 'Pet-Friendly Boarding House, Talisay', 'type' => 'Boarding House', 'barangay' => 'Poblacion', 'city' => 'Talisay City', 'lat' => 10.2447, 'lng' => 123.8494, 'rent' => 3500, 'units' => 3, 'amenities' => ['Pet Friendly', 'Wi-Fi'], 'rules' => $quiet, 'description' => 'Boarding house in Talisay where dogs and cats are welcome.'],
            ['owner' => 'ouano', 'title' => 'Dog and Cat Friendly Rooms, Tabunok', 'type' => 'Boarding House', 'barangay' => 'Tabunok', 'city' => 'Talisay City', 'lat' => 10.2538, 'lng' => 123.8512, 'rent' => 3800, 'units' => 2, 'amenities' => ['Pet Friendly', 'Wi-Fi', 'Parking Space'], 'rules' => $quiet, 'description' => 'Rooms with a small yard for pets. Parking beside the house.'],
            ['owner' => 'pepito', 'title' => 'Mandaue Apartment with Parking', 'type' => 'Apartment', 'barangay' => 'Centro', 'city' => 'Mandaue City', 'lat' => 10.3236, 'lng' => 123.9223, 'rent' => 6500, 'units' => 3, 'amenities' => ['Parking Space', 'Private Bathroom', 'Wi-Fi'], 'rules' => $quiet, 'description' => 'One-bedroom apartments with a parking slot, near the Mandaue public market.'],
            ['owner' => 'pepito', 'title' => 'Opon Road Apartment, Parking Included', 'type' => 'Apartment', 'barangay' => 'Opao', 'city' => 'Mandaue City', 'lat' => 10.3343, 'lng' => 123.9364, 'rent' => 6900, 'units' => 2, 'amenities' => ['Parking Space', 'Private Bathroom'], 'rules' => $quiet, 'description' => 'Apartment with a garage, close to the factories and malls of Mandaue.'],
            // Sort, bedrooms and deposit demos ("cheapest 2 bedroom apartment sa Banilad, walay deposit", "family of 4 ...").
            ['owner' => 'alcoseba', 'title' => 'Two-Bedroom Flat, Banilad (No Deposit)', 'type' => 'Apartment', 'barangay' => 'Banilad', 'city' => 'Cebu City', 'lat' => 10.3432, 'lng' => 123.9101, 'rent' => 14500, 'deposit' => 0, 'units' => 2, 'bedrooms' => 2, 'amenities' => ['Parking Space', 'Wi-Fi', 'Air Conditioning'], 'rules' => $quiet, 'description' => 'Two-bedroom flat for a small family or two workers. No security deposit: one month advance only.'],
            ['owner' => 'alcoseba', 'title' => 'Banilad Garden Apartment, 2 Bedrooms', 'type' => 'Apartment', 'barangay' => 'Banilad', 'city' => 'Cebu City', 'lat' => 10.3418, 'lng' => 123.9086, 'rent' => 12000, 'deposit' => 0, 'units' => 2, 'bedrooms' => 2, 'amenities' => ['Parking Space', 'Wi-Fi'], 'rules' => $quiet, 'description' => 'Quiet two-bedroom apartment behind a small garden. No deposit for the first lease.'],
            ['owner' => 'lozada', 'title' => 'Three-Bedroom Flat, Mabolo', 'type' => 'Apartment', 'barangay' => 'Mabolo', 'city' => 'Cebu City', 'lat' => 10.3143, 'lng' => 123.9148, 'rent' => 22000, 'deposit' => 22000, 'units' => 1, 'bedrooms' => 3, 'occupants' => 6, 'amenities' => ['Parking Space', 'Washing Machine', 'Air Conditioning'], 'rules' => $quiet, 'description' => 'Spacious three-bedroom flat for a family of up to six, near Robinsons Galleria.'],
            ['owner' => 'tudtud', 'title' => 'No-Deposit Bedspace Near Colon', 'type' => 'Bedspace', 'barangay' => 'Ermita', 'city' => 'Cebu City', 'lat' => 10.2961, 'lng' => 123.9015, 'rent' => 1700, 'deposit' => 0, 'units' => 4, 'amenities' => ['Electric Fan', 'Hot Shower'], 'rules' => $quiet, 'description' => 'Bedspace with no deposit, pay by the month. A few steps from Colon.'],
            ['owner' => 'yap', 'title' => 'Three-Bedroom House, Linao Talisay', 'type' => 'House', 'barangay' => 'Linao', 'city' => 'Talisay City', 'lat' => 10.2512, 'lng' => 123.8392, 'rent' => 9800, 'units' => 1, 'bedrooms' => 3, 'amenities' => ['Parking Space', 'Washing Machine', 'Private Kitchen'], 'rules' => $quiet, 'description' => 'Three-bedroom house for a family, with parking and a washing machine.'],
        ];
    }
}
