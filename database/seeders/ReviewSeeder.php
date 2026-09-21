<?php

namespace Database\Seeders;

use App\Models\Property;
use App\Models\Review;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Gives every property 3–6 tenant reviews, so ratings, "Popular places to
 * stay" and each landlord's score have data to show.
 *
 * `reviews` is unique on (tenant_id, property_id), so every property needs
 * distinct reviewers — hence the pool of 28 sample tenants below. Picks come
 * from the property id, so re-running gives the same result every time.
 * Comments are written per property type, and about a third get a landlord reply.
 */
class ReviewSeeder extends Seeder
{
    private const TENANTS = [
        ['Maria', 'Santos'], ['Juan', 'Dela Cruz'], ['Angelica', 'Reyes'], ['Mark', 'Villanueva'],
        ['Jasmine', 'Lim'], ['Carlo', 'Bacalso'], ['Nicole', 'Ortega'], ['Paolo', 'Tan'],
        ['Jhoanna', 'Cuizon'], ['Rey', 'Abellanosa'], ['Shaira', 'Mendoza'], ['Kenneth', 'Ybañez'],
        ['Mikaela', 'Lapinig'], ['Joel', 'Suson'], ['Trisha', 'Gonzales'], ['Arnel', 'Tumulak'],
        ['Cherry', 'Batiancila'], ['Dexter', 'Caballes'], ['Lovely', 'Pepito'], ['Nelson', 'Rosal'],
        ['Hazel', 'Ouano'], ['Vincent', 'Garcia'], ['Ana', 'Sarmiento'], ['Bryan', 'Yray'],
        ['Rose Ann', 'Lumapas'], ['Ferdinand', 'Ceniza'], ['Glaiza', 'Enriquez'], ['Tomas', 'Wenceslao'],
    ];

    /** type => rating => comments */
    private const COMMENTS = [
        'Bedspace' => [
            5 => [
                'Tahimik ug limpyo. Ang caretaker mabait ug dali ra kaayo kontakon.',
                'Very clean shared CR and the water never ran out, even in the morning rush. Safe for a girl coming home late.',
                'Best bedspace I have stayed in for the price. Locker has a lock and the fan is strong.',
                'Curfew is strict but that is why it feels safe. Ate caretaker treats everyone fairly.',
            ],
            4 => [
                'Good value for the price. Only the queue for the CR in the morning is long.',
                'Clean and quiet. Wi-Fi is weak on the top bunk area but I can live with it.',
                'Nice for a student budget. Would like a bigger locker.',
            ],
            3 => [
                'Okay for the price. A bit crowded when all beds are full.',
                'Decent but the room gets warm in the afternoon.',
            ],
            2 => ['Noisy from the street until midnight and water pressure is weak on weekends.'],
        ],
        'Boarding House' => [
            5 => [
                'Exactly like the photos. Landlord fixed the leaking faucet the same day I told them.',
                'Room is ventilated and quiet. Near jeepney stops so no need to walk far.',
                'Very fair with the electric bill, they show the meter reading every month.',
                'Stayed for a year. No issues on deposit return either, they refunded it in full.',
            ],
            4 => [
                'Good room, small but clean. Shared kitchen is well kept.',
                'Comfortable and safe. A bit far from the main road but tricycles are always around.',
                'Landlord is responsive. I wish there were more outlets in the room.',
            ],
            3 => [
                'Fine for the price. Some noise from the neighbors at night.',
                'Okay stay. A few repairs took a week to be done.',
            ],
            2 => ['Smaller than I expected and the water goes weak in the morning.'],
        ],
        'Apartment' => [
            5 => [
                'Spacious for the price and the guard is always at the gate. Landlord replies within minutes.',
                'Aircon and hot shower both work perfectly. Fiber Wi-Fi is fast enough for work calls.',
                'Great location. Walking distance to groceries and the jeepney line.',
                'Unit was clean and ready on move-in day. Very professional landlord.',
            ],
            4 => [
                'Nice apartment overall. Parking slots are limited so come early.',
                'Good unit, well kept. Water pressure is a little low on the upper floor.',
                'Comfortable and quiet. Deposit terms were clear from the start.',
            ],
            3 => [
                'Decent for the price. Street noise in the evening.',
                'Okay place, but I had to ask twice to get the aircon serviced.',
            ],
            2 => ['Smaller than it looked in the photos and the hallway smells damp after rain.'],
        ],
        'House' => [
            5 => [
                'Perfect for our family of five. Big yard, quiet neighborhood, kids can play outside.',
                'Landlord is very accommodating and fixed the gate lock right away.',
                'House was clean when we moved in. Neighbors are friendly.',
                'Good value for a whole house. Water supply is steady.',
            ],
            4 => [
                'Nice house with enough space for the family. A bit far from the highway.',
                'Well maintained. Yard needs regular trimming but that is part of the deal.',
                'Comfortable and safe. Rent is fair for the size.',
            ],
            3 => [
                'Decent house. The roof needed patching after the first heavy rain.',
                'Okay overall, some repairs took time.',
            ],
            2 => ['Electric bill was much higher than expected, and the water tank leaks.'],
        ],
    ];

    /** rating => landlord replies, used on roughly a third of reviews. */
    private const REPLIES = [
        5 => ['Salamat kaayo! Welcome back anytime.', 'Thank you for staying with us. Take care!'],
        4 => ['Thanks for the feedback. We will look into the storage and Wi-Fi.', 'Salamat! We will keep improving the place.'],
        3 => ['Thanks for telling us. We have followed up with the repairs.'],
        2 => ['Sorry about that. We have talked to the caretaker and checked the water line.'],
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
                    'contact_number' => sprintf('09175550%03d', $i),
                    'profile_picture' => 'https://i.pravatar.cc/300?img='.(($i * 5) % 70 + 1),
                    'account_status' => 'active',
                ]
            );
            UserRole::firstOrCreate(['user_id' => $user->user_id, 'role' => 'Tenant']);

            return $user;
        })->values();

        Property::query()->each(function (Property $property) use ($tenants) {
            $count = 3 + $property->property_id % 4;   // 3 to 6 reviews
            $type  = $property->property_type;

            for ($i = 0; $i < $count; $i++) {
                $tenant = $tenants[($property->property_id * 3 + $i) % $tenants->count()];
                $rating = $this->ratingFor($property->property_id, $i);
                $pool   = self::COMMENTS[$type === 'Condominium' ? 'Apartment' : $type][$rating];
                $days   = 3 + ($property->property_id * 7 + $i * 23) % 240;
                $reply  = ($property->property_id + $i) % 3 === 0 ? self::REPLIES[$rating] : null;

                $review = Review::firstOrNew(['tenant_id' => $tenant->user_id, 'property_id' => $property->property_id]);
                $review->fill([
                    'landlord_id'         => $property->landlord_id,
                    'rating'              => $rating,
                    'review_comment'      => $pool[($property->property_id + $i) % count($pool)],
                    'landlord_reply'      => $reply ? $reply[$i % count($reply)] : null,
                    'landlord_replied_at' => $reply ? now()->subDays(max(1, $days - 2)) : null,
                    'is_hidden'           => false,
                ]);
                // created_at isn't mass-assignable; set it directly so reviews spread over the past 8 months.
                $review->created_at = now()->subDays($days);
                $review->save();
            }
        });
    }

    /** Mostly 4–5 stars with the odd 3 or 2, like real listings. */
    private function ratingFor(int $propertyId, int $i): int
    {
        $weights = [5, 5, 4, 5, 4, 3, 5, 4, 2, 4, 5, 4];

        return $weights[($propertyId * 3 + $i * 7) % count($weights)];
    }
}
