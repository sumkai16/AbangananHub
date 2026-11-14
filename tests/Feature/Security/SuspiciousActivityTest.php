<?php

namespace Tests\Feature\Security;

use App\Models\AuthEvent;
use App\Models\Notification;
use App\Models\User;
use App\Services\Security\AuthEventRecorder;
use App\Services\Security\SuspiciousActivityMonitor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuspiciousActivityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // All the test requests share one IP; the per-IP cap would otherwise swallow the burst.
        config(['security.auth_events_ip_cap_per_minute' => 1000]);
    }

    private function admin(string $email): User
    {
        $admin = User::factory()->create(['email' => $email]);
        $admin->assignRole('Admin');

        return $admin;
    }

    private function alerts(User $user)
    {
        return Notification::where('user_id', $user->user_id)->where('title', 'Security alert')->get();
    }

    public function test_five_failures_alert_admins_once(): void
    {
        $adminA = $this->admin('a@example.com');
        $adminB = $this->admin('b@example.com');
        $recorder = app(AuthEventRecorder::class);

        for ($i = 0; $i < 7; $i++) {
            $recorder->record('login_failed', email: 'maria@example.com');
        }

        foreach ([$adminA, $adminB] as $admin) {
            $alerts = $this->alerts($admin);
            $this->assertCount(1, $alerts);
            $this->assertSame('account', $alerts[0]->type);
            $this->assertSame('5 failed sign-ins for maria@example.com in 15 minutes.', $alerts[0]->message);
            $this->assertStringContainsString('security-events', $alerts[0]->link);
            $this->assertStringContainsString('maria%40example.com', $alerts[0]->link);
        }
    }

    public function test_below_threshold_is_quiet(): void
    {
        $admin = $this->admin('a@example.com');
        $user = User::factory()->create();
        $recorder = app(AuthEventRecorder::class);

        for ($i = 0; $i < 4; $i++) {
            $recorder->record('login_failed', email: 'maria@example.com');
        }
        for ($i = 0; $i < 2; $i++) {
            $recorder->record('access_denied', $user);
            $recorder->record('two_factor_failed', $user);
        }
        // A lockout on an ordinary account is not worth a page.
        $recorder->record('lockout', $user);

        $this->assertCount(0, $this->alerts($admin));
    }

    public function test_old_failures_do_not_count(): void
    {
        $admin = $this->admin('a@example.com');
        for ($i = 0; $i < 4; $i++) {
            AuthEvent::create(['event' => 'login_failed', 'email' => 'maria@example.com'])
                ->forceFill(['created_at' => now()->subMinutes(30)])->save();
        }

        app(AuthEventRecorder::class)->record('login_failed', email: 'maria@example.com');

        $this->assertCount(0, $this->alerts($admin));
    }

    public function test_admin_lockout_alerts(): void
    {
        $admin = $this->admin('boss@example.com');

        app(AuthEventRecorder::class)->record('lockout', $admin);

        $alerts = $this->alerts($admin);
        $this->assertCount(1, $alerts);
        $this->assertSame('Admin account boss@example.com was locked out after repeated failed sign-ins.', $alerts[0]->message);
    }

    public function test_repeated_access_denied_and_two_factor_failures_alert(): void
    {
        $admin = $this->admin('boss@example.com');
        $user = User::factory()->create(['email' => 'maria@example.com']);
        $recorder = app(AuthEventRecorder::class);

        for ($i = 0; $i < 3; $i++) {
            $recorder->record('access_denied', $user);
            $recorder->record('two_factor_failed', $user);
        }

        $messages = $this->alerts($admin)->pluck('message')->all();
        $this->assertContains('3 blocked attempts to open admin pages by maria@example.com in 10 minutes.', $messages);
        $this->assertContains('3 wrong two-factor codes by maria@example.com in 10 minutes.', $messages);
        $this->assertCount(2, $messages);
    }

    public function test_monitor_failure_never_breaks_recording(): void
    {
        $this->app->bind(SuspiciousActivityMonitor::class, fn () => new class extends SuspiciousActivityMonitor {
            public function inspect(AuthEvent $event): void
            {
                throw new \RuntimeException('monitor down');
            }
        });

        $row = app(AuthEventRecorder::class)->record('login_failed', email: 'maria@example.com');

        $this->assertNotNull($row);
        $this->assertDatabaseHas('auth_events', ['event_id' => $row->event_id, 'email' => 'maria@example.com']);
    }
}
