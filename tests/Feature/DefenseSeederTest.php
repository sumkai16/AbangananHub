<?php

namespace Tests\Feature;

use App\Models\Property;
use App\Models\Reservation;
use App\Models\User;
use App\Services\AdminQueues;
use App\Services\LandlordNavCounts;
use App\Services\LandlordRentDueCount;
use App\Services\Security\TwoFactor;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DefenseAdminSeeder;
use Database\Seeders\DefenseRentalsSeeder;
use Database\Seeders\DefenseSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use PragmaRX\Google2FA\Google2FA;
use RuntimeException;
use Tests\TestCase;

/**
 * The whole defense world, built the way `php artisan migrate:fresh --seed` builds it (DatabaseSeeder, ending in
 * DefenseSeeder): the administrator's known two-factor secret, every admin queue and landlord sidebar number
 * non-zero, no photo that needs the internet, and a second run that adds nothing. The detail of each scenario is
 * asserted in DefenseAdminSeedTest and DefenseRentalsSeedTest.
 */
class DefenseSeederTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        config(['broadcasting.default' => 'null']);
    }

    public function test_the_full_seed_builds_a_world_where_every_queue_and_badge_has_something(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = AdminQueues::navCounts();
        foreach (['verifications', 'listings', 'documents', 'units', 'reports', 'disputes', 'payouts', 'holds'] as $key) {
            $this->assertGreaterThan(0, $admin[$key] ?? 0, "Admin queue '{$key}' is empty.");
        }

        $maria = User::where('email', 'landlord@abangananhub.com')->firstOrFail();
        foreach (LandlordNavCounts::for($maria->user_id) as $key => $count) {
            $this->assertGreaterThan(0, $count, "Maria's '{$key}' sidebar number is zero.");
        }
        $this->assertGreaterThan(0, LandlordRentDueCount::for($maria->user_id), "Maria's Payments number is zero.");

        $this->assertSame(0, DB::table('property_media')->where('media_url', 'like', '%unsplash%')->count());
        $this->assertSame(0, DB::table('unit_media')->where('media_url', 'like', '%unsplash%')->count());
        foreach (DB::table('property_media')->where('media_url', 'like', '/images/%')->pluck('media_url')->unique() as $path) {
            $this->assertFileExists(public_path($path));
        }
    }

    public function test_the_admin_keeps_two_factor_on_with_a_known_secret_and_recovery_codes(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::where('email', DefenseSeeder::ADMIN_EMAIL)->firstOrFail();
        $this->assertTrue($admin->hasTwoFactor());
        $this->assertSame(DefenseSeeder::ADMIN_TOTP_SECRET, $admin->two_factor_secret);

        // The code an authenticator app shows for the documented secret signs the admin in...
        $code = app(Google2FA::class)->getCurrentOtp(DefenseSeeder::ADMIN_TOTP_SECRET);
        $this->assertTrue(app(TwoFactor::class)->verify($admin, $code));

        // ...and so does each recovery code, once.
        $this->assertTrue(app(TwoFactor::class)->verify($admin->fresh(), DefenseSeeder::ADMIN_RECOVERY_CODES[0]));
        $this->assertFalse(app(TwoFactor::class)->verify($admin->fresh(), DefenseSeeder::ADMIN_RECOVERY_CODES[0]));
    }

    public function test_the_defense_code_command_prints_the_current_code(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->artisan('defense:code', ['--secret' => true])
            ->expectsOutputToContain(app(Google2FA::class)->getCurrentOtp(DefenseSeeder::ADMIN_TOTP_SECRET))
            ->expectsOutputToContain(DefenseSeeder::ADMIN_TOTP_SECRET)
            ->assertSuccessful();
    }

    public function test_running_the_defense_seed_again_adds_nothing(): void
    {
        $this->seed(DatabaseSeeder::class);
        $tables = ['users', 'properties', 'property_units', 'reservations', 'payments', 'payment_claims', 'conversations', 'messages', 'reports', 'reviews', 'viewing_requests', 'tenant_concerns', 'payouts', 'tenant_holds', 'deposit_charges', 'lease_templates'];
        $before = collect($tables)->mapWithKeys(fn ($t) => [$t => DB::table($t)->count()]);

        $this->seed(DefenseSeeder::class);

        foreach ($tables as $table) {
            $this->assertSame($before[$table], DB::table($table)->count(), "A second run changed '{$table}'.");
        }
    }

    public function test_it_refuses_to_run_in_production(): void
    {
        $this->app['env'] = 'production';

        $this->expectException(RuntimeException::class);
        (new DefenseSeeder())->run();
    }

    public function test_every_scenario_is_described_and_its_account_exists(): void
    {
        $this->seed(DatabaseSeeder::class);

        foreach ([DefenseAdminSeeder::SCENARIOS, DefenseRentalsSeeder::SCENARIOS] as $list) {
            $this->assertNotEmpty($list);
            foreach ($list as $scenario) {
                foreach (['key', 'who', 'where', 'shows'] as $field) {
                    $this->assertNotEmpty($scenario[$field] ?? null, "A scenario is missing '{$field}'.");
                }
                $this->assertTrue(User::where('email', $scenario['who'])->exists(), "Scenario '{$scenario['key']}' logs in as {$scenario['who']}, who does not exist.");
            }
        }
    }
}
