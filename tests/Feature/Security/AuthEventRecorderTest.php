<?php

namespace Tests\Feature\Security;

use App\Models\AuthEvent;
use App\Models\User;
use App\Services\Security\AuthEventRecorder;
use App\Support\UserAgentSummary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthEventRecorderTest extends TestCase
{
    use RefreshDatabase;

    public function test_records_event_with_request_context(): void
    {
        $user = User::factory()->create();

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9', 'HTTP_USER_AGENT' => 'TestAgent/1.0'])
            ->get('/login');
        // Build the request context the recorder reads.
        $request = \Illuminate\Http\Request::create('/login', 'GET', [], [], [], ['REMOTE_ADDR' => '203.0.113.9', 'HTTP_USER_AGENT' => 'TestAgent/1.0']);
        app()->instance('request', $request);

        $event = app(AuthEventRecorder::class)->record('login', $user);

        $this->assertNotNull($event);
        $this->assertSame($user->user_id, $event->user_id);
        $this->assertSame('203.0.113.9', $event->ip_address);
        $this->assertSame('TestAgent/1.0', $event->user_agent);
        $this->assertSame('web', $event->channel);
        $this->assertSame($user->email, $event->email);
    }

    public function test_write_failure_is_swallowed(): void
    {
        // A table that cannot be written (dropping it here would leak into later tests).
        AuthEvent::creating(fn () => throw new \RuntimeException('db down'));

        $this->assertNull(app(AuthEventRecorder::class)->record('login'));
    }

    public function test_prunes_rows_older_than_retention(): void
    {
        $old = AuthEvent::create(['event' => 'login']);
        $old->forceFill(['created_at' => now()->subDays(91)])->save();
        $recent = AuthEvent::create(['event' => 'login']);
        $recent->forceFill(['created_at' => now()->subDays(89)])->save();

        $this->artisan('model:prune', ['--model' => [AuthEvent::class]])->assertSuccessful();

        $this->assertDatabaseMissing('auth_events', ['event_id' => $old->event_id]);
        $this->assertDatabaseHas('auth_events', ['event_id' => $recent->event_id]);
    }

    public function test_warning_events_are_capped_per_ip_but_logins_are_not(): void
    {
        config(['security.auth_events_ip_cap_per_minute' => 3]);
        $recorder = app(AuthEventRecorder::class);

        foreach (range(1, 6) as $i) {
            $recorder->record('login_failed', email: "x{$i}@example.com");
        }
        foreach (range(1, 6) as $i) {
            $recorder->record('login');
        }

        $this->assertSame(3, AuthEvent::where('event', 'login_failed')->count());
        $this->assertSame(6, AuthEvent::where('event', 'login')->count());
    }

    public function test_user_agent_summary(): void
    {
        $this->assertSame('Chrome on Android', UserAgentSummary::from('Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 Chrome/126.0 Mobile Safari/537.36'));
        $this->assertSame('Safari on iOS', UserAgentSummary::from('Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 Version/17.0 Mobile/15E148 Safari/604.1'));
        $this->assertSame('Firefox on Windows', UserAgentSummary::from('Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:128.0) Gecko/20100101 Firefox/128.0'));
        $this->assertSame('Unknown device', UserAgentSummary::from(null));
    }
}
