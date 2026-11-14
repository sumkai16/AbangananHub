<?php

namespace Tests\Feature\Security;

use App\Models\AuditLog;
use App\Models\AuthEvent;
use App\Models\User;
use App\Services\Security\SessionManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SessionManagementTest extends TestCase
{
    use RefreshDatabase;

    private const CHROME_ANDROID = 'Mozilla/5.0 (Linux; Android 14) AppleWebKit/537.36 Chrome/120.0 Mobile Safari/537.36';

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        return $admin;
    }

    private function sessionRow(User $user, string $id, int $minutesAgo = 5, ?string $ua = self::CHROME_ANDROID): void
    {
        DB::table('sessions')->insert([
            'id'            => $id,
            'user_id'       => $user->user_id,
            'ip_address'    => '203.0.113.50',
            'user_agent'    => $ua,
            'payload'       => '',
            'last_activity' => now()->subMinutes($minutesAgo)->getTimestamp(),
        ]);
    }

    private function sessionCount(User $user): int
    {
        return DB::table('sessions')->where('user_id', $user->user_id)->count();
    }

    public function test_lists_current_and_other_sessions(): void
    {
        $user = User::factory()->create();
        $this->sessionRow($user, 'current-session', 1);
        $this->sessionRow($user, 'phone-session', 120);
        $token = $user->createToken('Pixel 8');

        $items = app(SessionManager::class)->forUser($user, 'current-session');

        $this->assertCount(3, $items);
        // Newest first: the app token was just created, then the current session (1 min), then the phone (2 h).
        $this->assertSame(['token:'.$token->accessToken->id, 'current-session', 'phone-session'], $items->pluck('id')->all());
        $this->assertSame([false, true, false], $items->pluck('is_current')->all());
        $this->assertSame('Chrome on Android', $items[2]->device);
        $this->assertSame('203.0.113.50', $items[2]->ip);
        $this->assertSame('Mobile app · Pixel 8', $items[0]->device);
        $this->assertNull($items[0]->ip);

        $this->actingAs($user)->get(route('profile.edit'))
            ->assertOk()
            ->assertSee("signed in")
            ->assertSee('Chrome on Android');

        // The admin has its own profile page; it shows the same card.
        $admin = $this->admin();
        $this->actingAs($admin)->get(route('admin.profile.edit'))
            ->assertOk()
            ->assertSee('Sign out other devices');
    }

    public function test_service_keeps_the_current_session_when_signing_out_others(): void
    {
        $user = User::factory()->create(['remember_token' => 'old-token']);
        $this->sessionRow($user, 'keep-me');
        $this->sessionRow($user, 'drop-me');

        $count = app(SessionManager::class)->revokeOthers($user, 'keep-me');

        $this->assertSame(1, $count);
        $this->assertSame(['keep-me'], DB::table('sessions')->where('user_id', $user->user_id)->pluck('id')->all());
        $this->assertNotSame('old-token', $user->fresh()->remember_token);
        $this->assertDatabaseHas('auth_events', ['user_id' => $user->user_id, 'event' => 'sessions_revoked']);
        $this->assertSame(1, AuthEvent::where('event', 'sessions_revoked')->first()->metadata['count']);
    }

    public function test_sign_out_others_keeps_current_session(): void
    {
        $user = User::factory()->create();
        $this->sessionRow($user, 'other-1');
        $this->sessionRow($user, 'other-2');

        // Wrong password: an error in the "sessions" bag and nothing deleted.
        $this->actingAs($user)
            ->delete(route('account.sessions.destroyOthers'), ['password' => 'not-the-password'])
            ->assertSessionHasErrors('password', null, 'sessions');
        $this->assertSame(2, $this->sessionCount($user));

        // Missing password is refused too.
        $this->actingAs($user)->delete(route('account.sessions.destroyOthers'))
            ->assertSessionHasErrors('password', null, 'sessions');
        $this->assertSame(2, $this->sessionCount($user));

        // Right password: the other devices go. (The test's own session is not a row in `sessions`.)
        $this->actingAs($user)
            ->delete(route('account.sessions.destroyOthers'), ['password' => 'password'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Signed out 2 other devices.');
        $this->assertSame(0, $this->sessionCount($user));
    }

    public function test_one_device_can_be_signed_out_with_the_password(): void
    {
        $user = User::factory()->create();
        $this->sessionRow($user, 'laptop');
        $this->sessionRow($user, 'tablet');

        $this->actingAs($user)->delete(route('account.sessions.destroy', 'laptop'), ['password' => 'wrong'])
            ->assertSessionHasErrors('password', null, 'sessions');
        $this->assertSame(2, $this->sessionCount($user));

        $this->actingAs($user)->delete(route('account.sessions.destroy', 'laptop'), ['password' => 'password'])
            ->assertSessionHas('success');
        $this->assertSame(['tablet'], DB::table('sessions')->where('user_id', $user->user_id)->pluck('id')->all());
    }

    public function test_cannot_delete_someone_elses_session(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->sessionRow($other, 'victim-session');
        $otherToken = $other->createToken('Their phone');

        $this->actingAs($user)->delete(route('account.sessions.destroy', 'victim-session'), ['password' => 'password'])
            ->assertNotFound();
        $this->actingAs($user)->delete(route('account.sessions.destroy', 'token:'.$otherToken->accessToken->id), ['password' => 'password'])
            ->assertNotFound();

        $this->assertSame(1, $this->sessionCount($other));
        $this->assertSame(1, $other->tokens()->count());
    }

    public function test_tokens_are_revoked_too(): void
    {
        $user = User::factory()->create();
        $user->createToken('Pixel 8');
        $user->createToken('iPad');

        $this->actingAs($user)
            ->delete(route('account.sessions.destroyOthers'), ['password' => 'password'])
            ->assertSessionHas('success', 'Signed out 2 other devices.');

        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_one_app_token_can_be_revoked(): void
    {
        $user = User::factory()->create();
        $keep = $user->createToken('iPad');
        $drop = $user->createToken('Pixel 8');

        $this->actingAs($user)->delete(route('account.sessions.destroy', 'token:'.$drop->accessToken->id), ['password' => 'password'])
            ->assertSessionHas('success');

        $this->assertSame([$keep->accessToken->id], $user->tokens()->pluck('id')->all());
    }

    public function test_admin_sign_out_everywhere_is_audited(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create();
        $this->sessionRow($user, 's1');
        $this->sessionRow($user, 's2');
        $user->createToken('Pixel 8');

        $this->actingAs($admin)->post(route('admin.users.signOutEverywhere', $user))
            ->assertSessionHas('success');

        $this->assertSame(0, $this->sessionCount($user));
        $this->assertSame(0, $user->tokens()->count());
        $log = AuditLog::where('action', 'user.sign_out_everywhere')->first();
        $this->assertNotNull($log);
        $this->assertSame($admin->user_id, $log->actor_id);
        $this->assertSame($user->user_id, $log->auditable_id);
        $this->assertDatabaseHas('auth_events', ['user_id' => $user->user_id, 'event' => 'sessions_revoked']);
    }

    public function test_admin_cannot_sign_out_self_everywhere(): void
    {
        $admin = $this->admin();
        $this->sessionRow($admin, 'admin-session');

        $this->actingAs($admin)->post(route('admin.users.signOutEverywhere', $admin))
            ->assertSessionHas('error', 'Use the Sign out button for your own account.');

        $this->assertSame(1, $this->sessionCount($admin));
        $this->assertDatabaseMissing('audit_logs', ['action' => 'user.sign_out_everywhere']);
    }

    public function test_non_admin_cannot_use_admin_route(): void
    {
        $user = User::factory()->create();
        $target = User::factory()->create();
        $this->sessionRow($target, 'target-session');

        $this->actingAs($user)->post(route('admin.users.signOutEverywhere', $target))->assertForbidden();

        $this->assertSame(1, $this->sessionCount($target));
    }
}
