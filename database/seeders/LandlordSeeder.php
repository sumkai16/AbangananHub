<?php

namespace Database\Seeders;

use App\Models\LandlordVerification;
use App\Models\RentalBusiness;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Seven more approved landlords, so listings are spread over different owners
 * (Maria Santos, from UserSeeder, is the eighth). Each one is approved the way
 * the admin flow does it: a Landlord role, an Approved verification and a
 * rental_businesses row. PropertySeeder finds them by these emails.
 *
 * Runs after UserSeeder (needs the admin as reviewer). Idempotent.
 */
class LandlordSeeder extends Seeder
{
    public const LANDLORDS = [
        [
            'first_name' => 'Ramon', 'last_name' => 'Alcoseba', 'email' => 'ramon.alcoseba@abangananhub.com',
            'contact' => '09173340101', 'id_type' => 'Driver\'s License',
            'business' => 'Alcoseba Rentals', 'address' => 'Subangdaku, Mandaue City, Cebu',
            'bio' => 'Retired seaman turned landlord. Owns boarding houses and apartments around Mandaue and the north.',
        ],
        [
            'first_name' => 'Teresita', 'last_name' => 'Ouano', 'email' => 'teresita.ouano@abangananhub.com',
            'contact' => '09173340102', 'id_type' => 'PhilSys National ID',
            'business' => 'Ouano Family Properties', 'address' => 'Pajo, Lapu-Lapu City, Cebu',
            'bio' => 'Family-run rentals in Mactan for workers at the export zone and nearby resorts.',
        ],
        [
            'first_name' => 'Gerardo', 'last_name' => 'Tudtud', 'email' => 'gerardo.tudtud@abangananhub.com',
            'contact' => '09173340103', 'id_type' => 'UMID',
            'business' => 'Tudtud Boarding Houses', 'address' => 'Tunghaan, Minglanilla, Cebu',
            'bio' => 'Boarding houses and small apartments in the south-Cebu corridor, from Talisay to Carcar.',
        ],
        [
            'first_name' => 'Lourdes', 'last_name' => 'Pepito', 'email' => 'lourdes.pepito@abangananhub.com',
            'contact' => '09173340104', 'id_type' => 'Passport',
            'business' => 'Pepito Homes North Cebu', 'address' => 'Poblacion, Danao City, Cebu',
            'bio' => 'Landlady in Danao, Compostela and Bogo. Quick to reply and fair with deposits.',
        ],
        [
            'first_name' => 'Anselmo', 'last_name' => 'Yap', 'email' => 'anselmo.yap@abangananhub.com',
            'contact' => '09173340105', 'id_type' => 'Driver\'s License',
            'business' => 'Yap Western Cebu Rentals', 'address' => 'Poblacion, Toledo City, Cebu',
            'bio' => 'Rooms and houses for power-plant and port workers around Toledo and Balamban.',
        ],
        [
            'first_name' => 'Cristina', 'last_name' => 'Gonzaga', 'email' => 'cristina.gonzaga@abangananhub.com',
            'contact' => '09173340106', 'id_type' => 'PhilSys National ID',
            'business' => 'Gonzaga South Cebu Stays', 'address' => 'Poblacion, Argao, Cebu',
            'bio' => 'Long-stay rooms for teachers, dive staff and remote workers along the south coast.',
        ],
        [
            'first_name' => 'Wilfredo', 'last_name' => 'Lozada', 'email' => 'wilfredo.lozada@abangananhub.com',
            'contact' => '09173340107', 'id_type' => 'UMID',
            'business' => 'Lozada Urban Living', 'address' => 'Apas, Cebu City, Cebu',
            'bio' => 'Studios and condo-type units around IT Park, Lahug and Banilad for young professionals.',
        ],
    ];

    public function run(): void
    {
        $admin = User::where('email', 'admin@abangananhub.com')->first();

        foreach (self::LANDLORDS as $i => $l) {
            $user = User::firstOrCreate(
                ['email' => $l['email']],
                [
                    'first_name'         => $l['first_name'],
                    'last_name'          => $l['last_name'],
                    'password'           => Hash::make('password'),
                    'contact_number'     => $l['contact'],
                    'gcash_number'       => $l['contact'],
                    'gcash_account_name' => $l['first_name'].' '.$l['last_name'],
                    'profile_picture'    => 'https://i.pravatar.cc/300?img='.(3 + $i * 9),
                    'bio'                => $l['bio'],
                    'profile_visibility' => 'public',
                    'account_status'     => 'active',
                ]
            );
            UserRole::firstOrCreate(['user_id' => $user->user_id, 'role' => 'Landlord']);

            LandlordVerification::firstOrCreate(
                ['user_id' => $user->user_id],
                [
                    'government_id'       => 'verifications/seed-placeholder-approved.jpg',
                    'id_type'             => $l['id_type'],
                    'selfie'              => 'verifications/seed-placeholder-selfie-approved.jpg',
                    'id_image_hash'       => hash('sha256', 'seed-landlord-'.$l['email']),
                    'verification_status' => 'Approved',
                    'reviewed_by'         => $admin?->user_id,
                    'reviewed_at'         => now()->subDays(40 + $i * 6),
                    'submitted_at'        => now()->subDays(45 + $i * 6),
                ]
            );

            RentalBusiness::firstOrCreate(
                ['landlord_id' => $user->user_id],
                [
                    'business_name'    => $l['business'],
                    'description'      => $l['bio'],
                    'contact_number'   => $l['contact'],
                    'business_address' => $l['address'],
                ]
            );
        }

        // Maria Santos (UserSeeder) is approved but has no business row yet.
        $maria = User::where('email', 'landlord@abangananhub.com')->first();
        if ($maria) {
            RentalBusiness::firstOrCreate(
                ['landlord_id' => $maria->user_id],
                [
                    'business_name'    => 'Santos Rentals Cebu',
                    'description'      => 'Bedspaces, rooms and apartments around Cebu City and Talisay.',
                    'contact_number'   => $maria->contact_number,
                    'business_address' => 'Labangon, Cebu City, Cebu',
                ]
            );
        }
    }
}
