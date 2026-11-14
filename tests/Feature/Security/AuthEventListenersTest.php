<?php

namespace Tests\Feature\Security;

use App\Models\AuthEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AuthEventListenersTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_login_records_login_event(): void
    {
        $user = User::factory()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'password']);

        $this->assertSame(1, AuthEvent::where('event', 'login')->where('user_id', $user->user_id)->count());
        $this->assertSame('web', AuthEvent::where('event', 'login')->first()->channel);
    }

    public function test_failed_login_never_stores_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'S3cret-Typed!']);

        $this->assertSame(1, AuthEvent::where('event', 'login_failed')->count());
        $this->assertSame($user->user_id, AuthEvent::where('event', 'login_failed')->first()->user_id);
        $this->assertSame(0, AuthEvent::where('metadata', 'like', '%S3cret%')->orWhere('email', 'like', '%S3cret%')->count());
    }

    public function test_failed_logins_are_capped_per_ip(): void
    {
        for ($i = 0; $i < 40; $i++) {
            // A different email each time so the login throttle (5 per email+IP) never kicks in.
            $this->post('/login', ['email' => "nobody{$i}@example.test", 'password' => 'S3cret-Typed!']);
        }

        $count = AuthEvent::where('event', 'login_failed')->count();
        $this->assertGreaterThan(0, $count);
        $this->assertLessThanOrEqual(30, $count);
    }

    public function test_lockout_is_recorded(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 6; $i++) {
            $this->post('/login', ['email' => $user->email, 'password' => 'wrong-password']);
        }

        $this->assertGreaterThanOrEqual(1, AuthEvent::where('event', 'lockout')->count());
        $this->assertSame($user->email, AuthEvent::where('event', 'lockout')->first()->email);
    }

    public function test_password_and_email_change_are_recorded(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->from('/profile')->put('/password', [
            'current_password'      => 'password',
            'password'              => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, AuthEvent::where('event', 'password_changed')->where('user_id', $user->user_id)->count());

        $oldEmail = $user->email;
        $this->actingAs($user->fresh())->patch(route('profile.update'), [
            'first_name'       => $user->first_name,
            'last_name'        => $user->last_name,
            'email'            => 'changed.address@example.test',
            'current_password' => 'new-password',
        ])->assertSessionHasNoErrors();

        $event = AuthEvent::where('event', 'email_changed')->where('user_id', $user->user_id)->first();
        $this->assertNotNull($event);
        $this->assertSame(['from' => $oldEmail, 'to' => 'changed.address@example.test'], $event->metadata);
    }

    public function test_api_login_is_recorded_with_api_channel(): void
    {
        $user = User::factory()->create();

        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'password', 'device_name' => 'pixel'])
            ->assertOk();
        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'S3cret-Typed!', 'device_name' => 'pixel'])
            ->assertStatus(422);

        $this->assertSame('api', AuthEvent::where('event', 'login')->where('user_id', $user->user_id)->value('channel'));
        $this->assertSame('api', AuthEvent::where('event', 'login_failed')->where('user_id', $user->user_id)->value('channel'));
        $this->assertSame(0, AuthEvent::where('metadata', 'like', '%S3cret%')->count());
    }

    public function test_admin_cannot_get_api_token(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        $this->postJson('/api/v1/auth/login', ['email' => $admin->email, 'password' => 'password', 'device_name' => 'pixel'])
            ->assertForbidden()
            ->assertExactJson(['message' => 'Admins sign in on the web.']);

        $this->assertSame(0, $admin->tokens()->count());
    }
}
