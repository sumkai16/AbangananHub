<?php

namespace Tests\Feature\Holds;

use App\Models\AuditLog;
use App\Models\AuthEvent;
use App\Models\HoldMatchAlert;
use App\Models\Notification;
use App\Models\TenantHold;
use App\Models\User;
use App\Services\Holds\HoldMatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HoldMatcherTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $landlord;
    private User $held;
    private TenantHold $hold;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');
        $this->landlord = User::factory()->create();
        $this->landlord->assignRole('Landlord');

        $this->held = User::factory()->create([
            'first_name' => 'Juan', 'last_name' => 'Dela Cruz', 'contact_number' => '0917 123 4567',
        ]);
        $this->held->assignRole('Tenant');
        $this->hold = $this->holdFor($this->held, TenantHold::ACTIVE);
    }

    private function holdFor(User $tenant, string $status): TenantHold
    {
        return TenantHold::create([
            'tenant_id' => $tenant->user_id, 'landlord_id' => $this->landlord->user_id, 'amount_owed' => 5000,
            'reason' => 'Two months unpaid.', 'status' => $status, 'flagged_at' => now(),
        ]);
    }

    private function newcomer(array $attrs = []): User
    {
        $user = User::factory()->create(array_merge(['contact_number' => '0999 000 1111'], $attrs));
        $user->assignRole('Tenant');

        return $user;
    }

    private function heldSignedIn(string $device, string $ip): void
    {
        AuthEvent::create([
            'user_id' => $this->held->user_id, 'email' => $this->held->email, 'event' => 'login',
            'channel' => 'web', 'device_id' => $device, 'ip_address' => $ip,
        ]);
    }

    private function inspect(User $user, ?string $device = null, ?string $ip = null)
    {
        return app(HoldMatcher::class)->inspect($user, $device, $ip);
    }

    /** @dataProvider phoneFormats */
    public function test_phone_alerts_in_every_format(string $typed): void
    {
        $alerts = $this->inspect($this->newcomer(['contact_number' => $typed]));

        $this->assertCount(1, $alerts);
        $this->assertSame(['phone'], $alerts->first()->signals);
    }

    public static function phoneFormats(): array
    {
        return [['09171234567'], ['+63 917 123 4567'], ['63917-123-4567']];
    }

    public function test_a_different_phone_does_not_alert(): void
    {
        $this->assertCount(0, $this->inspect($this->newcomer(['contact_number' => '09171234568'])));
    }

    public function test_device_alone_alerts(): void
    {
        $this->heldSignedIn('device-held', '10.0.0.1');

        $alerts = $this->inspect($this->newcomer(), 'device-held', '10.9.9.9');

        $this->assertSame(['device'], $alerts->first()->signals);
    }

    public function test_name_and_ip_together_alert(): void
    {
        $this->heldSignedIn('device-held', '10.0.0.1');

        $alerts = $this->inspect($this->newcomer(['first_name' => 'JUAN', 'last_name' => 'dela  cruz']), 'other-device', '10.0.0.1');

        $this->assertCount(1, $alerts);
        $this->assertEqualsCanonicalizing(['name', 'ip'], $alerts->first()->signals);
    }

    public function test_ip_alone_never_alerts_the_roommate_case(): void
    {
        $this->heldSignedIn('device-held', '10.0.0.1');

        $this->assertCount(0, $this->inspect($this->newcomer(), 'other-device', '10.0.0.1'));
        $this->assertSame(0, HoldMatchAlert::count());
    }

    public function test_name_alone_never_alerts(): void
    {
        $this->heldSignedIn('device-held', '10.0.0.1');

        $this->assertCount(0, $this->inspect($this->newcomer(['first_name' => 'Juan', 'last_name' => 'Dela Cruz']), 'other-device', '10.7.7.7'));
    }

    public function test_a_pending_hold_is_ignored(): void
    {
        $this->hold->update(['status' => TenantHold::PENDING]);

        $this->assertCount(0, $this->inspect($this->newcomer(['contact_number' => '09171234567'])));
    }

    public function test_the_held_tenant_never_matches_themselves(): void
    {
        $this->assertCount(0, $this->inspect($this->held));
    }

    public function test_a_second_check_makes_no_second_alert_or_notification(): void
    {
        $user = $this->newcomer(['contact_number' => '09171234567']);

        $this->inspect($user);
        $adminNotices = Notification::where('user_id', $this->admin->user_id)->count();
        $landlordNotices = Notification::where('user_id', $this->landlord->user_id)->count();

        $again = $this->inspect($user);

        $this->assertCount(0, $again);
        $this->assertSame(1, HoldMatchAlert::count());
        $this->assertSame($adminNotices, Notification::where('user_id', $this->admin->user_id)->count());
        $this->assertSame($landlordNotices, Notification::where('user_id', $this->landlord->user_id)->count());
    }

    public function test_a_later_signal_is_merged_without_a_new_notification(): void
    {
        $this->heldSignedIn('device-held', '10.0.0.1');
        $user = $this->newcomer(['contact_number' => '09171234567']);
        $this->inspect($user);
        $notices = Notification::count();

        $this->inspect($user, 'device-held');

        $this->assertEqualsCanonicalizing(['phone', 'device'], HoldMatchAlert::first()->signals);
        $this->assertSame($notices, Notification::count());
    }

    public function test_admin_and_landlord_are_told_and_the_landlord_learns_nothing_about_the_new_account(): void
    {
        $user = $this->newcomer(['first_name' => 'Zed', 'last_name' => 'Quux', 'contact_number' => '09171234567']);

        $this->inspect($user);

        $admin = Notification::where('user_id', $this->admin->user_id)->where('title', 'Possible duplicate account')->first();
        $this->assertNotNull($admin);
        $this->assertStringContainsString('same phone number', $admin->message);
        $this->assertSame('/admin/tenant-holds?tab=duplicates', $admin->link);

        $landlord = Notification::where('user_id', $this->landlord->user_id)->where('title', 'Possible duplicate account')->first();
        $this->assertSame('A possible duplicate account of Juan Dela Cruz was found. An admin is reviewing it.', $landlord->message);
        foreach (['Zed', 'Quux', $user->email, 'phone', '0917'] as $leak) {
            $this->assertStringNotContainsString($leak, $landlord->message);
        }
    }

    public function test_the_flag_is_audited_as_the_system_with_signals_only(): void
    {
        $user = $this->newcomer(['contact_number' => '09171234567']);
        $this->actingAs($user);   // the person who just signed up must not be blamed in the log

        $this->inspect($user);

        $row = AuditLog::where('action', 'hold.duplicate_flagged')->first();
        $this->assertSame('System', $row->actor_name);
        $this->assertSame(['signals' => ['phone'], 'source' => 'signup'], $row->metadata);
    }

    public function test_dismiss_closes_the_alert_and_audits(): void
    {
        $alert = $this->inspect($this->newcomer(['contact_number' => '09171234567']))->first();
        $this->actingAs($this->admin);

        $dismissed = app(HoldMatcher::class)->dismiss($alert, $this->admin);

        $this->assertSame(HoldMatchAlert::DISMISSED, $dismissed->status);
        $this->assertSame($this->admin->user_id, $dismissed->reviewed_by);
        $this->assertNotNull($dismissed->reviewed_at);
        $this->assertSame(1, AuditLog::where('action', 'hold.duplicate_dismissed')->count());
        // A dismissed alert stays dismissed when the same account matches again.
        $this->inspect($alert->user);
        $this->assertSame(HoldMatchAlert::DISMISSED, $alert->fresh()->status);
    }

    public function test_put_on_hold_creates_a_linked_active_hold_and_a_neutral_notice(): void
    {
        $user = $this->newcomer(['contact_number' => '09171234567']);
        $alert = $this->inspect($user)->first();
        $this->actingAs($this->admin);

        $copy = app(HoldMatcher::class)->putOnHold($alert, $this->admin);

        $this->assertSame(TenantHold::ACTIVE, $copy->status);
        $this->assertSame($user->user_id, $copy->tenant_id);
        $this->assertNull($copy->landlord_id);
        $this->assertNull($copy->reservation_id);
        $this->assertSame($this->hold->hold_id, $copy->source_hold_id);
        $this->assertSame($this->admin->user_id, $copy->confirmed_by);
        $this->assertSame(HoldMatchAlert::HELD, $alert->fresh()->status);
        $this->assertTrue($user->fresh()->isOnHold());
        $this->assertSame(1, AuditLog::where('action', 'hold.duplicate_held')->count());

        $notice = Notification::where('user_id', $user->user_id)->first();
        $this->assertNotNull($notice);
        foreach (['Juan', 'Dela Cruz', 'duplicate', 'hold'] as $leak) {
            $this->assertStringNotContainsStringIgnoringCase($leak, $notice->title.' '.$notice->message);
        }

        // Doing it twice does not stack holds.
        $this->assertSame($copy->hold_id, app(HoldMatcher::class)->putOnHold($alert->fresh(), $this->admin)->hold_id);
    }

    public function test_sign_up_through_the_website_raises_an_alert(): void
    {
        $this->post('/register', [
            'first_name' => 'Pedro', 'last_name' => 'Penduko', 'email' => 'pedro@example.test',
            'contact_number' => '+639171234567', 'password' => 'Str0ng-Pass-Word-91', 'password_confirmation' => 'Str0ng-Pass-Word-91',
        ])->assertRedirect();

        $this->assertSame(1, HoldMatchAlert::count());
        $this->assertSame(User::where('email', 'pedro@example.test')->value('user_id'), HoldMatchAlert::first()->user_id);
    }

    public function test_sign_up_through_the_api_raises_an_alert(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'first_name' => 'Pedro', 'last_name' => 'Penduko', 'email' => 'pedro@example.test',
            'contact_number' => '09171234567', 'password' => 'Str0ng-Pass-Word-91', 'password_confirmation' => 'Str0ng-Pass-Word-91',
            'device_name' => 'test phone',
        ])->assertCreated();

        $this->assertSame(1, HoldMatchAlert::count());
    }

    public function test_sign_up_still_works_when_the_matcher_throws(): void
    {
        $this->mock(HoldMatcher::class)->shouldReceive('inspect')->andThrow(new \RuntimeException('boom'));

        $this->post('/register', [
            'first_name' => 'Pedro', 'last_name' => 'Penduko', 'email' => 'pedro@example.test',
            'contact_number' => '09171234567', 'password' => 'Str0ng-Pass-Word-91', 'password_confirmation' => 'Str0ng-Pass-Word-91',
        ])->assertRedirect();

        $this->assertNotNull(User::where('email', 'pedro@example.test')->first());
        $this->assertAuthenticated();
        $this->assertSame(0, HoldMatchAlert::count());
    }

    public function test_a_sign_in_from_a_new_browser_is_checked(): void
    {
        $this->heldSignedIn('device-held', '10.0.0.1');
        $user = $this->newcomer();
        $earlier = AuthEvent::create(['user_id' => $user->user_id, 'email' => $user->email, 'event' => 'login', 'channel' => 'web', 'device_id' => 'device-old']);
        $earlier->forceFill(['created_at' => now()->subDays(5)])->save();

        // The new account signs in from the held tenant's browser: a new device for them, a known one for the held tenant.
        $this->withCookie(config('security.device_cookie'), 'device-held')
            ->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect();

        $this->assertSame(['device'], HoldMatchAlert::first()->signals);
    }

    public function test_a_walk_in_tenant_is_matched_on_phone_only(): void
    {
        $this->landlord->update(['account_status' => 'active']);
        $this->heldSignedIn('device-held', '10.0.0.1');

        // Same name and (test) IP, different phone: no alert. Same phone: alert.
        $walkIn = User::factory()->create(['first_name' => 'Juan', 'last_name' => 'Dela Cruz', 'contact_number' => '09175550000', 'is_walk_in' => true, 'account_status' => 'inactive']);
        HoldMatcher::inspectAfterResponse($walkIn, null, null, 'walk-in');
        app(\Illuminate\Support\Defer\DeferredCallbackCollection::class)->invoke();
        $this->assertSame(0, HoldMatchAlert::count());

        $walkIn->update(['contact_number' => '+63 917 123 4567']);
        HoldMatcher::inspectAfterResponse($walkIn, null, null, 'walk-in');
        app(\Illuminate\Support\Defer\DeferredCallbackCollection::class)->invoke();
        $this->assertSame(1, HoldMatchAlert::count());
        $this->assertSame('walk-in', AuditLog::where('action', 'hold.duplicate_flagged')->first()->metadata['source']);
    }
}
