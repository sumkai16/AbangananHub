<?php

namespace Tests\Feature\Security;

use App\Mail\NewSignInMail;
use App\Models\AuthEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class NewSignInAlertTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function earlierLogin(User $user, string $deviceId, int $daysAgo = 5): void
    {
        $event = AuthEvent::create([
            'user_id' => $user->user_id, 'email' => $user->email, 'event' => 'login',
            'channel' => 'web', 'device_id' => $deviceId,
        ]);
        $event->forceFill(['created_at' => now()->subDays($daysAgo)])->save();
    }

    /** Sign in carrying a device cookie. withCookie() encrypts it like a real browser's cookie. */
    private function signIn(User $user, ?string $deviceId)
    {
        $client = $deviceId ? $this->withCookie(config('security.device_cookie'), $deviceId) : $this;

        return $client->post('/login', ['email' => $user->email, 'password' => 'password']);
    }

    public function test_first_ever_login_sends_nothing(): void
    {
        Mail::fake();
        $user = $this->userWithRole('Landlord');

        $this->signIn($user, 'device-a')->assertRedirect();

        Mail::assertNothingSent();
        Mail::assertNothingQueued();
    }

    public function test_known_device_sends_nothing(): void
    {
        Mail::fake();
        $user = $this->userWithRole('Landlord');
        $this->earlierLogin($user, 'device-a');

        $this->signIn($user, 'device-a');

        $this->assertSame(2, AuthEvent::where('user_id', $user->user_id)->where('event', 'login')->count());
        Mail::assertNothingSent();
    }

    public function test_new_device_landlord_gets_alert(): void
    {
        Mail::fake();
        $user = $this->userWithRole('Landlord');
        $this->earlierLogin($user, 'device-a');

        $this->withHeader('User-Agent', 'Mozilla/5.0 (Linux; Android 14) AppleWebKit/537.36 Chrome/120.0 Mobile Safari/537.36');
        $this->signIn($user, 'device-b');

        Mail::assertSent(NewSignInMail::class, function (NewSignInMail $mail) use ($user) {
            return $mail->hasTo($user->email)
                && $mail->device === 'Chrome on Android'
                && $mail->resetUrl === route('password.request')
                && $mail->when !== '';
        });
    }

    public function test_old_device_beyond_90_days_counts_as_new(): void
    {
        Mail::fake();
        $user = $this->userWithRole('Admin');
        $this->earlierLogin($user, 'device-a', daysAgo: 120);
        $this->earlierLogin($user, 'device-z', daysAgo: 2);

        $this->signIn($user, 'device-a');

        Mail::assertSent(NewSignInMail::class);
    }

    public function test_alert_email_renders_without_location(): void
    {
        $html = (new NewSignInMail('Nov 14, 2026 at 3:11 PM', 'Chrome on Android', '203.0.113.9'))->render();

        $this->assertStringContainsString('Nov 14, 2026 at 3:11 PM', $html);
        $this->assertStringContainsString('Chrome on Android', $html);
        $this->assertStringContainsString('203.0.113.9', $html);
        $this->assertStringContainsString('If this was you, you can ignore this email.', $html);
        $this->assertStringContainsString('Reset my password', $html);
    }

    public function test_tenant_gets_no_alert(): void
    {
        Mail::fake();
        $user = $this->userWithRole('Tenant');
        $this->earlierLogin($user, 'device-a');

        $this->signIn($user, 'device-b');

        Mail::assertNothingSent();
    }

    public function test_mail_failure_does_not_break_login(): void
    {
        $user = $this->userWithRole('Landlord');
        $this->earlierLogin($user, 'device-a');
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('smtp down'));

        $this->signIn($user, 'device-b')->assertRedirect();

        $this->assertAuthenticatedAs($user);
    }
}
