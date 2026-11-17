<?php

namespace Database\Seeders;

use App\Models\Amenity;
use App\Models\LandlordVerification;
use App\Models\Notification;
use App\Models\Property;
use App\Models\PropertyMedia;
use App\Models\PropertyUnit;
use App\Models\RentalBusiness;
use App\Models\Review;
use App\Models\TenantDetail;
use App\Models\UnitMedia;
use App\Models\User;
use App\Models\UserRole;
use App\Support\PropertyPolicies;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

/**
 * Cast for the promo video: landlady Rosa Abellana and tenant Mika Dela Cruz.
 *
 *  - Rosa: approved landlord with a payout QR and one Minglanilla apartment. 3 units are rented by seeded tenants
 *    (paid, overdue, due soon) for the ledger and analytics shots; 2 are free, and Mika reserves Room 2B on camera.
 *  - Mika: a clean tenant account. Favorites, chat, reservation, payment and move-in all happen live while recording.
 *  - Carlo and Trisha: past tenants with a review each (one with Rosa's reply), so the listing shows ratings.
 *
 * Not part of DatabaseSeeder. Run on its own, after the base seed:
 *     php artisan db:seed --class=PromoSeeder
 *
 * Re-run it to reset between takes: it deletes Mika's account (her chats, reservation, payments and favorites go
 * with it by cascade), clears Rosa's notifications, removes anything Rosa added on camera, and frees the units again.
 * Nothing outside these accounts is touched. No profile photos, so no real faces appear in the video.
 */
class PromoSeeder extends TenantSeeder
{
    private const ROSA = [
        'first_name' => 'Rosa', 'last_name' => 'Abellana', 'email' => 'rosa@abangananhub.com',
        'contact' => '09173340201', 'business' => 'Abellana Apartments',
        'bio' => 'Owns a small apartment building in Minglanilla. Has rented to students and young workers for 15 years.',
    ];

    private const MIKA = [
        'first_name' => 'Mika', 'last_name' => 'Dela Cruz', 'email' => 'mika@abangananhub.com',
        'contact' => '09173340202',
        'bio' => 'New grad starting my first job in Talisay. Quiet, tidy, and looking for a safe room near the highway.',
    ];

    private const PROPERTY_TITLE = 'Abellana Apartments, Minglanilla';

    /** [label, rent, occupancy, floor, sqm, tenant key or null (free)] */
    private const UNITS = [
        ['Room 1A', 5500, 1, 'Ground floor', 18, 'paid'],
        ['Room 1B', 5500, 1, 'Ground floor', 18, 'overdue'],
        ['Room 2A', 6000, 2, '2nd floor', 22, 'due_soon'],
        ['Room 2B', 6000, 2, '2nd floor', 22, null],   // Mika's room
        ['Room 3A', 6500, 2, '3rd floor', 24, null],
    ];

    /** Rosa's tenants. Same shape as TenantSeeder::TENANTS, so rent() writes the ledger the way the app does. */
    private const TENANTS = [
        'paid' => [
            'first_name' => 'Joanna', 'last_name' => 'Ybañez', 'email' => 'joanna.promo@abangananhub.com',
            'contact' => '09173340211', 'months_in' => 5, 'occupants' => 1, 'due_day' => 1,
            'pay_offsets' => [0, -1, 0, 0, 0], 'cash_every' => 0, 'methods' => ['GCash'],
            'advance' => 0, 'current' => 'paid',
        ],
        'overdue' => [
            'first_name' => 'Paolo', 'last_name' => 'Sarmiento', 'email' => 'paolo.promo@abangananhub.com',
            'contact' => '09173340212', 'months_in' => 4, 'occupants' => 1, 'due_day' => 5,
            'pay_offsets' => [2, 4, 0, 3], 'cash_every' => 2, 'methods' => ['GCash'],
            'advance' => 0, 'current' => 'unpaid',
        ],
        'due_soon' => [
            'first_name' => 'Bea', 'last_name' => 'Lumapas', 'email' => 'bea.promo@abangananhub.com',
            'contact' => '09173340213', 'months_in' => 3, 'occupants' => 2, 'due_day' => 20,
            'pay_offsets' => [0, -2, 0], 'cash_every' => 0, 'methods' => ['Maya', 'GCash'],
            'advance' => 0, 'current' => 'unpaid',
        ],
    ];

    /**
     * Two former tenants who reviewed the building after moving out, so the listing shows real ratings on camera.
     * [first, last, email, contact, rating, comment, Rosa's reply or null, months since the review]
     */
    private const PAST_TENANTS = [
        ['Carlo', 'Mendoza', 'carlo.promo@abangananhub.com', '09173340214', 5,
            'Stayed 10 months. Rosa answers fast, the room was exactly like the photos, and the gate rule kept the place quiet.',
            'Salamat, Carlo! Good luck sa bago nimo nga trabaho.', 2],
        ['Trisha', 'Villarin', 'trisha.promo@abangananhub.com', '09173340215', 4,
            'Clean building, short walk to the highway. Electricity is sub-metered, so watch your usage. Rosa gave receipts every month.',
            null, 5],
    ];

    public function run(): void
    {
        $admin = User::where('email', 'admin@abangananhub.com')->firstOrFail();

        $this->resetMika();
        $rosa = $this->rosa($admin);
        Notification::where('user_id', $rosa->user_id)->delete();

        $property = $this->property($rosa);
        $this->units($property);
        $this->reviews($property, $rosa);
        $this->mika();
    }

    /** Idempotent: one review per past tenant per property (the table's own UNIQUE), updated in place on re-run. */
    private function reviews(Property $property, User $rosa): void
    {
        foreach (self::PAST_TENANTS as [$first, $last, $email, $contact, $rating, $comment, $reply, $monthsAgo]) {
            $tenant = User::firstOrCreate(['email' => $email], [
                'first_name'         => $first,
                'last_name'          => $last,
                'password'           => Hash::make('password'),
                'contact_number'     => $contact,
                'profile_visibility' => 'public',
                'account_status'     => 'active',
            ]);
            UserRole::firstOrCreate(['user_id' => $tenant->user_id, 'role' => 'Tenant']);

            $at = now()->subMonths($monthsAgo);
            $review = Review::updateOrCreate(
                ['tenant_id' => $tenant->user_id, 'property_id' => $property->property_id],
                [
                    'landlord_id'         => $rosa->user_id,
                    'rating'              => $rating,
                    'review_comment'      => $comment,
                    'landlord_reply'      => $reply,
                    'landlord_replied_at' => $reply ? $at->copy()->addDay() : null,
                    'is_hidden'           => false,
                ]
            );
            $review->forceFill(['created_at' => $at, 'updated_at' => $at])->saveQuietly();
        }
    }

    /** Deleting the account cascades her conversations, messages, reservations, payments, claims and favorites. */
    private function resetMika(): void
    {
        User::where('email', self::MIKA['email'])->first()?->delete();
    }

    private function rosa(User $admin): User
    {
        $rosa = User::firstOrCreate(['email' => self::ROSA['email']], [
            'first_name'         => self::ROSA['first_name'],
            'last_name'          => self::ROSA['last_name'],
            'password'           => Hash::make('password'),
            'contact_number'     => self::ROSA['contact'],
            'bio'                => self::ROSA['bio'],
            'profile_visibility' => 'public',
            'account_status'     => 'active',
        ]);
        UserRole::firstOrCreate(['user_id' => $rosa->user_id, 'role' => 'Landlord']);
        if (! $rosa->payoutAccount()->exists()) {
            $rosa->savePayoutAccount(self::ROSA['contact'], 'ROSA ABELLANA');
        }

        // Same sample images the admin review screen shows for the other seeded landlords.
        $idPath     = 'verifications/seed-placeholder-rosa-id.jpg';
        $selfiePath = 'verifications/seed-placeholder-rosa-selfie.jpg';
        Storage::disk('local')->put($idPath, file_get_contents(__DIR__.'/assets/sample-id.jpg'));
        Storage::disk('local')->put($selfiePath, file_get_contents(__DIR__.'/assets/sample-selfie.jpg'));

        LandlordVerification::firstOrCreate(['user_id' => $rosa->user_id], [
            'government_id'       => $idPath,
            'id_type'             => 'PhilSys National ID',
            'selfie'              => $selfiePath,
            'id_image_hash'       => hash('sha256', 'seed-landlord-'.self::ROSA['email']),
            'verification_status' => 'Approved',
            'reviewed_by'         => $admin->user_id,
            'reviewed_at'         => now()->subMonths(6),
            'submitted_at'        => now()->subMonths(6)->subDays(2),
        ]);

        RentalBusiness::firstOrCreate(['landlord_id' => $rosa->user_id], [
            'business_name'    => self::ROSA['business'],
            'description'      => self::ROSA['bio'],
            'contact_number'   => self::ROSA['contact'],
            'business_address' => 'Poblacion Ward II, Minglanilla, Cebu',
        ]);

        return $rosa;
    }

    private function property(User $rosa): Property
    {
        // Anything Rosa added on camera in an earlier take goes, so S4 can be recorded again.
        Property::where('landlord_id', $rosa->user_id)->where('title', '!=', self::PROPERTY_TITLE)->get()->each->delete();

        $property = Property::updateOrCreate(
            ['landlord_id' => $rosa->user_id, 'title' => self::PROPERTY_TITLE],
            [
                'description'                  => 'A clean three-storey apartment building a two-minute walk from the Minglanilla highway, with jeepneys to Talisay and Cebu City at the corner. Rosa lives on the ground floor. Water and Wi-Fi are included; electricity is sub-metered.',
                'house_rules'                  => PropertyPolicies::compileRules(['No Smoking', 'Quiet Hours', 'No Overnight Guests'], 'Gate closes at 10:30 PM. Text Rosa if you will be late.'),
                'property_type'                => 'Apartment',
                'living_arrangement'           => 'Private',
                'occupancy_preference'         => 'No Preference',
                'water_included'               => true,
                'electricity_included'         => false,
                'internet_included'            => true,
                'association_fees_included'    => false,
                'utilities_separately_metered' => true,
                'address'                      => 'Poblacion Ward II, Minglanilla, Cebu',
                'city_municipality'            => 'Minglanilla',
                'barangay'                     => 'Poblacion Ward II',
                'latitude'                     => 10.2447,
                'longitude'                    => 123.7962,
                'verification_status'          => 'Approved',
                'publication_status'           => 'Published',
            ]
        );

        if ($property->wasRecentlyCreated) {
            $ids = Amenity::forProperty()->whereIn('amenity_name', ['CCTV', 'Gated Entrance', 'Near Public Transport', 'Laundry Area', 'Rooftop Access'])->pluck('amenity_id');
            $property->amenities()->attach($ids->all());

            foreach (['1522708323590-d24dbb6b0267', '1502672260266-1c1ef2d93688', '1560448204-603b3fc33ddc'] as $photo) {
                PropertyMedia::create([
                    'property_id' => $property->property_id,
                    'media_type'  => 'Image',
                    'media_url'   => "https://images.unsplash.com/photo-{$photo}?w=1200&q=80",
                ]);
            }
        }

        return $property;
    }

    private function units(Property $property): void
    {
        $labels = array_column(self::UNITS, 0);
        $property->units()->whereNotIn('unit_label', $labels)->get()->each->delete();

        $unitPhotos = ['1522771739844-6a9f6d5f14af', '1616594039964-ae9021a400a0', '1560185007-cde436f6a4d0', '1505693416388-ac5ce068fe85', '1615874959474-d609969a20ed'];
        $unitAmenities = Amenity::forUnit()->whereIn('amenity_name', ['Wi-Fi', 'Private Bathroom', 'Electric Fan', 'Bed Included', 'Wardrobe / Cabinet'])->pluck('amenity_id');

        foreach (self::UNITS as $i => [$label, $rent, $occupancy, $floor, $sqm, $tenantKey]) {
            $unit = PropertyUnit::firstOrCreate(
                ['property_id' => $property->property_id, 'unit_label' => $label],
                [
                    'floor'               => $floor,
                    'bedrooms'            => 1,
                    'bathrooms'           => 1,
                    'floor_area_sqm'      => $sqm,
                    'furnishing_status'   => 'Semi-furnished',
                    'description'         => 'Bright room with its own comfort room, a bed frame, a wardrobe and a window facing the hills. Sub-metered electricity.',
                    'rental_fee'          => $rent,
                    'security_deposit'    => $rent,
                    'occupancy_limit'     => $occupancy,
                    'availability_status' => 'Available',
                    'verification_status' => 'Approved',
                ]
            );

            if ($unit->wasRecentlyCreated) {
                foreach (range(0, 2) as $k) {
                    UnitMedia::create([
                        'unit_id'    => $unit->unit_id,
                        'media_type' => 'Image',
                        'media_url'  => 'https://images.unsplash.com/photo-'.$unitPhotos[($i + $k) % count($unitPhotos)].'?w=800&q=80',
                        'source'     => 'camera',
                        'caption'    => $k === 0 ? 'Sleeping area with natural light' : null,
                    ]);
                }
                $unit->amenities()->attach($unitAmenities->all());
            }

            if ($tenantKey === null) {
                // Free room: undo whatever Mika's take left behind.
                $unit->update(['availability_status' => 'Available']);
                continue;
            }

            $t = self::TENANTS[$tenantKey];
            $tenant = User::firstOrCreate(['email' => $t['email']], [
                'first_name'         => $t['first_name'],
                'last_name'          => $t['last_name'],
                'password'           => Hash::make('password'),
                'contact_number'     => $t['contact'],
                'profile_visibility' => 'public',
                'account_status'     => 'active',
            ]);
            UserRole::firstOrCreate(['user_id' => $tenant->user_id, 'role' => 'Tenant']);

            if (! $unit->reservations()->where('tenant_id', $tenant->user_id)->where('rental_status', 'Occupied')->exists()) {
                $this->rent($tenant, $unit->load('property'), $t);
            }
        }
    }

    private function mika(): void
    {
        $mika = User::create([
            'first_name'         => self::MIKA['first_name'],
            'last_name'          => self::MIKA['last_name'],
            'email'              => self::MIKA['email'],
            'password'           => Hash::make('password'),
            'contact_number'     => self::MIKA['contact'],
            'bio'                => self::MIKA['bio'],
            'profile_visibility' => 'public',
            'account_status'     => 'active',
        ]);
        UserRole::create(['user_id' => $mika->user_id, 'role' => 'Tenant']);

        // Filled in, so signing the agreement on camera isn't blocked by the tenant-details form.
        TenantDetail::saveFor($mika, [
            'emergency_contact_name'         => 'Liza Dela Cruz',
            'emergency_contact_relationship' => 'Parent',
            'emergency_contact_number'       => '09173340299',
            'id_type'                        => 'PhilSys',
            'id_number'                      => '1234-5678-9012-3456',
        ]);
    }
}
