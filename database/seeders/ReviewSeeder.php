<?php

namespace Database\Seeders;

use App\Models\Property;
use App\Models\Review;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Gives every property 3–5 tenant reviews so ratings, the "Popular places to
 * stay" ranking and landlord scores have data to show.
 *
 * `reviews` is unique on (tenant_id, property_id), so each property needs
 * distinct reviewers — hence the small pool of sample tenants below. Picks
 * are derived from the property id, so re-running is idempotent and gives
 * the same result every time.
 */
class ReviewSeeder extends Seeder
{
    private const TENANTS = [
        ['Maria', 'Santos'], ['Juan', 'Dela Cruz'], ['Angelica', 'Reyes'], ['Mark', 'Villanueva'],
        ['Jasmine', 'Lim'], ['Carlo', 'Bacalso'], ['Nicole', 'Ortega'], ['Paolo', 'Tan'],
    ];

    /** rating => sample comments */
    private const COMMENTS = [
        5 => [
            'Super clean and the landlord replies fast. Would happily stay again.',
            'Exactly as pictured. Quiet area, good water pressure and easy commute.',
            'Great value for the price. Felt safe coming home late.',
            'Very responsive owner and the place was ready on move-in day.',
        ],
        4 => [
            'Nice place overall. Wi-Fi is a bit slow at night but everything else is good.',
            'Comfortable and well kept. A little far from the main road.',
            'Good deal, clean rooms. Would like more storage space.',
        ],
        3 => [
            'Decent for the price. Some noise from the street in the evening.',
            'Okay stay. Needed a few small repairs but they were fixed eventually.',
        ],
        2 => [
            'Smaller than I expected and the water was weak in the mornings.',
        ],
    ];

    public function run(): void
    {
        $tenants = collect(self::TENANTS)->map(function ($name, $i) {
            $user = User::firstOrCreate(
                ['email' => 'reviewer'.($i + 1).'@abangananhub.com'],
                [
                    'first_name'     => $name[0],
                    'last_name'      => $name[1],
                    'password'       => Hash::make('password'),
                    'contact_number' => '0917000000'.$i,
                    'account_status' => 'active',
                ]
            );
            UserRole::firstOrCreate(['user_id' => $user->user_id, 'role' => 'Tenant']);

            return $user;
        })->values();

        Property::query()->each(function (Property $property) use ($tenants) {
            $count = 3 + $property->property_id % 3;   // 3, 4 or 5 reviews

            for ($i = 0; $i < $count; $i++) {
                $tenant = $tenants[($property->property_id + $i) % $tenants->count()];
                $rating = $this->ratingFor($property->property_id, $i);
                $pool = self::COMMENTS[$rating];

                Review::updateOrCreate(
                    ['tenant_id' => $tenant->user_id, 'property_id' => $property->property_id],
                    [
                        'landlord_id'    => $property->landlord_id,
                        'rating'         => $rating,
                        'review_comment' => $pool[($property->property_id + $i) % count($pool)],
                        'is_hidden'      => false,
                        'created_at'     => now()->subDays(3 + ($property->property_id * 7 + $i * 11) % 120),
                    ]
                );
            }
        });
    }

    /** Mostly 4–5 stars with the occasional 3 or 2, like real listings. */
    private function ratingFor(int $propertyId, int $i): int
    {
        $weights = [5, 5, 4, 5, 4, 3, 5, 4, 2, 4];

        return $weights[($propertyId * 3 + $i * 7) % count($weights)];
    }
}
