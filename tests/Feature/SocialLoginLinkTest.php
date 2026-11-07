<?php

namespace Tests\Feature;

use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Socialite\Facades\Socialite;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\TestCase;

/**
 * Social login only links to an existing account whose email is verified, and never to a walk-in
 * placeholder. Otherwise anyone could pre-register a victim's address and inherit their Google login.
 */
class SocialLoginLinkTest extends TestCase
{
    use CreatesMarketplaceFixtures, DatabaseTransactions;

    private const REFUSED = 'An account with this email already exists. Log in with your email and password (and verify your email) instead.';

    private function fakeSocialUser(string $provider, string $id, string $email, bool $stateless = false, bool $emailVerified = true): void
    {
        // A real Socialite user (not a mock of the contract) so getRaw() exists, as it does for Google.
        $social = (new \Laravel\Socialite\Two\User)
            ->setRaw(['email_verified' => $emailVerified])
            ->map(['id' => $id, 'nickname' => null, 'name' => 'Sam Social', 'email' => $email, 'avatar' => null]);

        Socialite::shouldReceive('driver')->with($provider)->andReturnSelf();

        if ($stateless) {
            Socialite::shouldReceive('stateless')->andReturnSelf();
            Socialite::shouldReceive('userFromToken')->andReturn($social);
        } else {
            Socialite::shouldReceive('user')->andReturn($social);
        }
    }

    public function test_an_unverified_account_is_not_linked_and_gets_no_social_row(): void
    {
        $victim = User::factory()->unverified()->create();
        $victim->assignRole('Tenant');

        $this->fakeSocialUser('google', 'google-attacker', $victim->email);

        $this->get('/auth/google/callback')
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', self::REFUSED);

        $this->assertGuest();
        $this->assertSame(0, $victim->socialAccounts()->count());
        $this->assertDatabaseMissing('social_accounts', ['provider_id' => 'google-attacker']);
    }

    public function test_a_verified_account_is_linked_and_logged_in(): void
    {
        $user = $this->makeTenant();

        $this->fakeSocialUser('google', 'google-verified', $user->email);

        $this->get('/auth/google/callback')->assertRedirect();

        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('social_accounts', ['user_id' => $user->user_id, 'provider' => 'google', 'provider_id' => 'google-verified']);
    }

    public function test_a_walk_in_placeholder_is_never_linked(): void
    {
        $walkIn = User::factory()->create(['is_walk_in' => true]);

        $this->fakeSocialUser('google', 'google-walkin', $walkIn->email);

        $this->get('/auth/google/callback')->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertSame(0, $walkIn->socialAccounts()->count());
    }

    public function test_an_inactive_account_is_never_linked(): void
    {
        $inactive = User::factory()->create(['account_status' => 'inactive']);

        $this->fakeSocialUser('google', 'google-inactive', $inactive->email);

        $this->get('/auth/google/callback')->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertDatabaseMissing('social_accounts', ['provider_id' => 'google-inactive']);
    }

    public function test_a_new_email_creates_a_verified_tenant_account(): void
    {
        $this->fakeSocialUser('google', 'google-new', 'brand-new-link-test@example.test');

        $this->get('/auth/google/callback')->assertRedirect();

        $user = User::where('email', 'brand-new-link-test@example.test')->firstOrFail();
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue($user->hasRole('Tenant'));
        $this->assertDatabaseHas('social_accounts', ['user_id' => $user->user_id, 'provider_id' => 'google-new']);
    }

    public function test_a_provider_that_does_not_vouch_for_the_email_creates_an_unverified_account(): void
    {
        $this->fakeSocialUser('facebook', 'fb-new', 'fb-new-link-test@example.test');

        $this->get('/auth/facebook/callback')->assertRedirect();

        $user = User::where('email', 'fb-new-link-test@example.test')->firstOrFail();
        $this->assertNull($user->email_verified_at, 'only Google reports a confirmed address');
        $this->assertDatabaseHas('social_accounts', ['user_id' => $user->user_id, 'provider_id' => 'fb-new']);
    }

    public function test_a_known_provider_identity_still_resolves_even_if_the_account_is_unverified(): void
    {
        $user = User::factory()->unverified()->create();
        $user->assignRole('Tenant');
        $user->socialAccounts()->create(['provider' => 'google', 'provider_id' => 'google-known']);

        $this->fakeSocialUser('google', 'google-known', 'changed-at-provider@example.test');

        $this->get('/auth/google/callback')->assertRedirect();

        $this->assertAuthenticatedAs($user);
        $this->assertSame(1, SocialAccount::where('provider_id', 'google-known')->count());
    }

    public function test_the_mobile_token_endpoint_refuses_an_unverified_account_with_409(): void
    {
        $victim = User::factory()->unverified()->create();
        $victim->assignRole('Tenant');

        $this->fakeSocialUser('google', 'google-mobile-attacker', $victim->email, stateless: true);

        $this->postJson('/api/v1/auth/google/token', ['token' => 'abc', 'device_name' => 'phone'])
            ->assertStatus(409)
            ->assertJson(['message' => self::REFUSED]);

        $this->assertSame(0, $victim->socialAccounts()->count());
        $this->assertSame(0, $victim->tokens()->count());
    }

    public function test_the_mobile_token_endpoint_signs_in_a_verified_account(): void
    {
        $user = $this->makeTenant();

        $this->fakeSocialUser('google', 'google-mobile-ok', $user->email, stateless: true);

        $this->postJson('/api/v1/auth/google/token', ['token' => 'abc', 'device_name' => 'phone'])
            ->assertOk()
            ->assertJsonStructure(['user', 'token', 'roles']);

        $this->assertDatabaseHas('social_accounts', ['user_id' => $user->user_id, 'provider_id' => 'google-mobile-ok']);
    }
}
