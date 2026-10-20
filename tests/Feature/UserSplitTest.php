<?php

namespace Tests\Feature;

use App\Http\Resources\UserResource;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\UserDevice;
use App\Services\ExpoPushNotifier;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Mockery;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\TestCase;

/**
 * Payout details, social logins and push devices live in their own tables instead of on `users`: a landlord's
 * GCash number never rides along on a nested user payload, a second provider adds a row instead of replacing the
 * first, and a second phone no longer steals the first one's pushes.
 */
class UserSplitTest extends TestCase
{
    use CreatesMarketplaceFixtures, DatabaseTransactions;

    // ─── payout account ─────────────────────────────────────────

    public function test_saving_a_payout_account_creates_updates_and_clears_it(): void
    {
        $landlord = $this->makeLandlord();
        $this->assertFalse($landlord->hasPayoutDestination());

        $landlord->savePayoutAccount('09171234567', 'Lana Landlord');
        $this->assertTrue($landlord->fresh()->hasPayoutDestination());
        $this->assertDatabaseHas('user_payout_accounts', ['user_id' => $landlord->user_id, 'gcash_number' => '09171234567']);

        $landlord->savePayoutAccount('09999999999', 'Lana Landlord');
        $this->assertSame(1, $landlord->payoutAccount()->count());
        $this->assertSame('09999999999', $landlord->fresh()->payoutAccount->gcash_number);

        $landlord->savePayoutAccount(null, '');
        $this->assertDatabaseMissing('user_payout_accounts', ['user_id' => $landlord->user_id]);
        $this->assertFalse($landlord->fresh()->hasPayoutDestination());
    }

    public function test_a_payout_destination_needs_both_the_number_and_the_name(): void
    {
        $landlord = $this->makeLandlord();

        $landlord->savePayoutAccount('09171234567', null);

        $this->assertFalse($landlord->fresh()->hasPayoutDestination());
    }

    public function test_the_landlord_profile_form_saves_and_clears_the_payout_account(): void
    {
        $landlord = $this->makeLandlord();
        $base = ['first_name' => $landlord->first_name, 'last_name' => $landlord->last_name];

        $this->actingAs($landlord)
            ->patch('/landlord/profile', $base + ['gcash_number' => '09171234567', 'gcash_account_name' => 'Lana Landlord']);
        $this->assertTrue($landlord->fresh()->hasPayoutDestination());

        $this->actingAs($landlord)
            ->patch('/landlord/profile', $base + ['gcash_number' => '', 'gcash_account_name' => '']);
        $this->assertFalse($landlord->fresh()->hasPayoutDestination());
    }

    public function test_a_field_left_out_of_the_api_update_keeps_its_stored_value(): void
    {
        $landlord = $this->makeLandlord();
        $landlord->savePayoutAccount('09171234567', 'Lana Landlord');

        Sanctum::actingAs($landlord, ['*']);
        $this->patchJson('/api/v1/landlord/profile/me', [
            'first_name'         => $landlord->first_name,
            'last_name'          => $landlord->last_name,
            'gcash_account_name' => 'Lana L.',
        ])->assertOk();

        $account = $landlord->fresh()->payoutAccount;
        $this->assertSame('09171234567', $account->gcash_number);
        $this->assertSame('Lana L.', $account->gcash_account_name);
    }

    public function test_a_nested_user_never_carries_payout_or_provider_fields(): void
    {
        $landlord = $this->makeLandlord();
        $landlord->savePayoutAccount('09171234567', 'Lana Landlord');
        $landlord->socialAccounts()->create(['provider' => 'google', 'provider_id' => 'g-1']);

        $nested = (new UserResource(User::find($landlord->user_id)))->resolve();

        foreach (['gcash_number', 'gcash_account_name', 'provider', 'provider_id', 'expo_push_token'] as $key) {
            $this->assertArrayNotHasKey($key, $nested);
        }
    }

    public function test_a_users_own_record_carries_the_payout_fields_when_the_relation_is_loaded(): void
    {
        $landlord = $this->makeLandlord();
        $landlord->savePayoutAccount('09171234567', 'Lana Landlord');

        $own = (new UserResource(User::with('payoutAccount')->find($landlord->user_id)))->resolve();

        $this->assertSame('09171234567', $own['gcash_number']);
        $this->assertSame('Lana Landlord', $own['gcash_account_name']);

        Sanctum::actingAs($landlord, ['*']);
        $this->getJson('/api/v1/profile')->assertOk()->assertJsonPath('data.user.gcash_number', '09171234567');
    }

    // ─── push devices ───────────────────────────────────────────

    public function test_a_user_can_register_several_devices(): void
    {
        $tenant = $this->makeTenant();
        Sanctum::actingAs($tenant, ['*']);

        $this->patchJson('/api/v1/profile/push-token', ['expo_push_token' => 'ExponentPushToken[phone-1]'])->assertOk();
        $this->patchJson('/api/v1/profile/push-token', ['expo_push_token' => 'ExponentPushToken[phone-2]'])->assertOk();
        $this->patchJson('/api/v1/profile/push-token', ['expo_push_token' => 'ExponentPushToken[phone-2]'])->assertOk();

        $this->assertSame(2, $tenant->devices()->count());
    }

    public function test_a_token_that_changes_hands_moves_to_the_new_user(): void
    {
        $first = $this->makeTenant();
        $second = $this->makeTenant();

        Sanctum::actingAs($first, ['*']);
        $this->patchJson('/api/v1/profile/push-token', ['expo_push_token' => 'ExponentPushToken[shared]'])->assertOk();

        Sanctum::actingAs($second, ['*']);
        $this->patchJson('/api/v1/profile/push-token', ['expo_push_token' => 'ExponentPushToken[shared]'])->assertOk();

        $this->assertSame(0, $first->devices()->count());
        $this->assertSame(1, $second->devices()->count());
        $this->assertSame(1, UserDevice::where('expo_push_token', 'ExponentPushToken[shared]')->count());
    }

    public function test_logout_unregisters_the_device_it_names(): void
    {
        $tenant = $this->makeTenant();
        $tenant->devices()->create(['expo_push_token' => 'ExponentPushToken[a]']);
        $tenant->devices()->create(['expo_push_token' => 'ExponentPushToken[b]']);

        Sanctum::actingAs($tenant, ['*']);
        $this->postJson('/api/v1/auth/logout', ['expo_push_token' => 'ExponentPushToken[a]'])->assertOk();

        $this->assertSame(['ExponentPushToken[b]'], $tenant->devices()->pluck('expo_push_token')->all());
    }

    public function test_a_push_goes_to_every_device_and_drops_the_dead_one(): void
    {
        $tenant = $this->makeTenant();
        $tenant->devices()->create(['expo_push_token' => 'ExponentPushToken[alive]']);
        $tenant->devices()->create(['expo_push_token' => 'ExponentPushToken[dead]']);

        Http::fake(['exp.host/*' => Http::response(['data' => [
            ['status' => 'ok', 'id' => 'x'],
            ['status' => 'error', 'message' => 'gone', 'details' => ['error' => 'DeviceNotRegistered']],
        ]])]);

        app(ExpoPushNotifier::class)->send($tenant, 'Title', 'Body');

        Http::assertSent(fn ($request) => collect($request->data())->pluck('to')->all() === [
            'ExponentPushToken[alive]', 'ExponentPushToken[dead]',
        ]);
        $this->assertSame(['ExponentPushToken[alive]'], $tenant->devices()->pluck('expo_push_token')->all());
    }

    public function test_a_user_with_no_device_sends_nothing(): void
    {
        Http::fake();

        app(ExpoPushNotifier::class)->send($this->makeTenant(), 'Title', 'Body');

        Http::assertNothingSent();
    }

    // ─── social accounts ────────────────────────────────────────

    public function test_a_social_login_for_an_existing_email_adds_a_row_and_never_overwrites_one(): void
    {
        $user = $this->makeTenant();
        $user->socialAccounts()->create(['provider' => 'google', 'provider_id' => 'google-original']);

        $this->fakeSocialUser('facebook', 'facebook-1', $user->email);
        $this->get('/auth/facebook/callback')->assertRedirect();

        $this->fakeSocialUser('google', 'google-different', $user->email);
        $this->get('/auth/google/callback')->assertRedirect();

        $this->assertSame(
            ['facebook' => 'facebook-1', 'google' => 'google-original'],
            $user->socialAccounts()->pluck('provider_id', 'provider')->sortKeys()->all()
        );
    }

    public function test_a_known_provider_identity_resolves_to_the_same_account(): void
    {
        $user = $this->makeTenant();
        $user->socialAccounts()->create(['provider' => 'google', 'provider_id' => 'google-1']);
        $before = User::count();

        $this->fakeSocialUser('google', 'google-1', 'renamed-at-provider@example.test');
        $this->get('/auth/google/callback')->assertRedirect();

        $this->assertAuthenticatedAs($user);
        $this->assertSame($before, User::count());
    }

    public function test_a_brand_new_social_login_creates_the_user_and_its_social_account(): void
    {
        $this->fakeSocialUser('google', 'google-new', 'brand-new-social@example.test');
        $this->get('/auth/google/callback')->assertRedirect();

        $user = User::where('email', 'brand-new-social@example.test')->firstOrFail();
        $this->assertSame(1, $user->socialAccounts()->count());
        $this->assertDatabaseHas('social_accounts', ['user_id' => $user->user_id, 'provider' => 'google', 'provider_id' => 'google-new']);
        $this->assertSame(1, SocialAccount::where('provider_id', 'google-new')->count());
    }

    private function fakeSocialUser(string $provider, string $id, string $email): void
    {
        $social = Mockery::mock(SocialiteUser::class);
        $social->shouldReceive('getId')->andReturn($id);
        $social->shouldReceive('getEmail')->andReturn($email);
        $social->shouldReceive('getName')->andReturn('Sam Social');
        $social->shouldReceive('getAvatar')->andReturn(null);

        Socialite::shouldReceive('driver')->with($provider)->andReturnSelf();
        Socialite::shouldReceive('user')->andReturn($social);
    }
}
