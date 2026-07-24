<?php

namespace App\Console\Commands;

use App\Models\Property;
use App\Models\Review;
use App\Models\Reservation;
use App\Models\TenantRating;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

/**
 * Browsable rating data for the Overall Ratings feature.
 *
 * The seeded database ships no reviews or tenant_ratings, so every ratings
 * surface renders empty. This fills both, with enough spread that the platform
 * averages, distributions and leaderboards are actually worth looking at.
 *
 * Tagged and additive: fixture reviews/ratings carry a marker in their comment
 * and fixture reviewers sit on a fixture email domain, so --clean removes
 * exactly what it made. Refuses to run in production.
 */
class RatingScenarios extends Command
{
    protected $signature = 'ratings:scenarios {--clean : remove all rating fixtures and exit}';

    protected $description = 'Seed reviews and tenant ratings for the Overall Ratings feature';

    private const TAG = '[ratings-fixture]';
    private const DOMAIN = 'ratings-fixture.test';
    private const PASSWORD = 'ratings-test-1234';

    public function handle(): int
    {
        if (app()->isProduction()) {
            $this->error('rating fixtures refuse to run in production.');

            return self::FAILURE;
        }

        if ($this->option('clean')) {
            [$r, $t, $u] = $this->purge();
            $this->info("Removed {$r} review(s), {$t} tenant rating(s) and {$u} fixture reviewer(s).");

            return self::SUCCESS;
        }

        $this->purge();

        $reviewers = $this->makeReviewers(6);
        $reviews = $this->seedReviews($reviewers);
        $ratings = $this->seedTenantRatings();

        $this->newLine();
        $this->line('  <fg=cyan;options=bold>Rating fixtures created</>');
        $this->newLine();
        $this->line("  Reviews (tenant \u{2192} property): <options=bold>{$reviews}</>");
        $this->line("  Tenant ratings (landlord \u{2192} tenant): <options=bold>{$ratings}</>");
        $this->newLine();
        $this->line('  Platform review avg:  ' . round((float) Review::where('is_hidden', false)->avg('rating'), 2));
        $this->line('  Platform tenant avg:  ' . round((float) TenantRating::avg('rating'), 2));
        $this->newLine();
        $this->line('  Admin ratings:  /admin/ratings');
        $this->line('  Remove with <options=bold>php artisan ratings:scenarios --clean</>');
        $this->newLine();

        return self::SUCCESS;
    }

    /**
     * Registered tenants who leave the property reviews. Real users (active,
     * not walk-ins) so they read as genuine reviewers.
     */
    private function makeReviewers(int $n): array
    {
        $first = ['Ana', 'Ben', 'Cara', 'Dino', 'Ella', 'Fritz', 'Gina', 'Hugo'];
        $reviewers = [];

        for ($i = 0; $i < $n; $i++) {
            $user = User::create([
                'first_name'     => $first[$i] ?? ('Rev' . $i),
                'last_name'      => 'Reviewer',
                'email'          => 'reviewer' . $i . '@' . self::DOMAIN,
                'password'       => Hash::make(self::PASSWORD),
                'account_status' => 'active',
            ]);

            if (! UserRole::where('user_id', $user->user_id)->where('role', 'Tenant')->exists()) {
                (new UserRole())->forceFill(['user_id' => $user->user_id, 'role' => 'Tenant', 'assigned_at' => now()])->save();
            }

            $reviewers[] = $user;
        }

        return $reviewers;
    }

    /**
     * Give each approved property a handful of reviews. Each property gets a
     * quality bias so the landlord/property leaderboards actually differ instead
     * of everything clustering at one number.
     */
    private function seedReviews(array $reviewers): int
    {
        $comments = [
            'Clean, well-kept, and exactly as listed.',
            'Great location, responsive landlord.',
            'Decent place but a few maintenance delays.',
            'Comfortable stay overall, would recommend.',
            'Not bad, though the photos were a bit generous.',
            'Landlord was accommodating throughout.',
            'Good value for the area.',
            'Some issues at move-in but resolved quickly.',
        ];

        $count = 0;
        $properties = Property::where('verification_status', 'Approved')->get();

        foreach ($properties as $index => $property) {
            // Bias: roughly every third property runs a little lower so the
            // lowest-rated leaderboard is not empty.
            $bias = $index % 3 === 0 ? [2, 3, 3, 4, 4] : [3, 4, 4, 5, 5];

            // 2–5 distinct reviewers per property.
            $picked = collect($reviewers)->shuffle()->take(rand(2, min(5, count($reviewers))));

            foreach ($picked as $reviewer) {
                $exists = Review::where('tenant_id', $reviewer->user_id)
                    ->where('property_id', $property->property_id)->exists();
                if ($exists) {
                    continue;
                }

                Review::create([
                    'tenant_id'      => $reviewer->user_id,
                    'property_id'    => $property->property_id,
                    'landlord_id'    => $property->landlord_id,
                    'rating'         => $bias[array_rand($bias)],
                    'review_comment' => self::TAG . ' ' . $comments[array_rand($comments)],
                    'is_hidden'      => false,
                ]);
                $count++;
            }
        }

        return $count;
    }

    /**
     * Rate the tenant on every occupied/completed reservation that has no
     * rating yet — this is the landlord -> tenant direction, and it populates
     * real tenants' received ratings (walk-ins included, which a landlord can
     * legitimately rate).
     */
    private function seedTenantRatings(): int
    {
        $comments = [
            'Paid on time, took care of the unit.',
            'Communicative and respectful tenant.',
            'A few late payments but otherwise fine.',
            'No complaints, would rent to again.',
            'Left the unit in good condition.',
        ];

        $count = 0;
        $reservations = Reservation::whereIn('rental_status', ['Occupied', 'Completed'])
            ->whereDoesntHave('tenantRating')
            ->with('property')
            ->get();

        foreach ($reservations as $reservation) {
            $landlordId = $reservation->property?->landlord_id;
            if (! $landlordId) {
                continue;
            }

            TenantRating::create([
                'reservation_id' => $reservation->reservation_id,
                'landlord_id'    => $landlordId,
                'tenant_id'      => $reservation->tenant_id,
                'rating'         => [3, 4, 4, 5, 5][array_rand([3, 4, 4, 5, 5])],
                'comment'        => self::TAG . ' ' . $comments[array_rand($comments)],
            ]);
            $count++;
        }

        return $count;
    }

    /**
     * @return array{0:int,1:int,2:int} deleted [reviews, tenantRatings, users]
     */
    private function purge(): array
    {
        $reviews = Review::where('review_comment', 'like', self::TAG . '%')->delete();
        $ratings = TenantRating::where('comment', 'like', self::TAG . '%')->delete();
        // Any reviews left by fixture reviewers are swept by the tenant_id
        // cascade when the users go.
        $users = 0;
        foreach (User::where('email', 'like', '%@' . self::DOMAIN)->get() as $user) {
            $user->delete();
            $users++;
        }

        return [$reviews, $ratings, $users];
    }
}
