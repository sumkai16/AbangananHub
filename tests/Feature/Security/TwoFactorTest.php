<?php

namespace Tests\Feature\Security;

use App\Models\AuditLog;
use App\Models\AuthEvent;
use App\Models\User;
use App\Services\Security\TwoFactor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class TwoFactorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['security.admin_two_factor' => 'required']);
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        return $admin;
    }

    /** An admin with two-factor already on. Returns [admin, secret, plain recovery codes]. */
    private function adminWithTwoFactor(): array
    {
        $admin = $this->admin();
        $secret = app(TwoFactor::class)->newSecret();
        $codes = [];
        $this->assertTrue(app(TwoFactor::class)->enable($admin, $secret, app(Google2FA::class)->getCurrentOtp($secret), $codes));

        return [$admin->fresh(), $secret, $codes];
    }

    private function otp(string $secret): string
    {
        return app(Google2FA::class)->getCurrentOtp($secret);
    }

    /** A valid code one 30-second step ahead: enable() has already spent the current step. */
    private function nextOtp(string $secret): string
    {
        $google2fa = app(Google2FA::class);

        return $google2fa->oathTotp($secret, $google2fa->getTimestamp() + 1);
    }

    public function test_admin_without_2fa_is_forced_to_setup(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.dashboard'))
            ->assertRedirect(route('admin.security.two-factor'));

        $this->actingAs($admin)->get(route('admin.security.two-factor'))
            ->assertOk()
            ->assertSee('Scan this code')
            ->assertSee('<svg', false);
    }

    public function test_setup_enables_two_factor_and_shows_codes_once(): void
    {
        $admin = $this->admin();

        $page = $this->actingAs($admin)->get(route('admin.security.two-factor'));
        $page->assertOk();
        $secret = \Illuminate\Support\Facades\Crypt::decryptString(session('two_factor.pending_secret'));

        // Wrong code: stays off.
        $this->post(route('admin.security.two-factor.store'), ['code' => '000000'])
            ->assertSessionHasErrors('code');
        $this->assertFalse($admin->fresh()->hasTwoFactor());

        $this->post(route('admin.security.two-factor.store'), ['code' => $this->otp($secret)])
            ->assertRedirect(route('admin.security.two-factor'))
            ->assertSessionHas('two_factor_codes');

        $fresh = $admin->fresh();
        $this->assertTrue($fresh->hasTwoFactor());
        $this->assertSame($secret, $fresh->two_factor_secret);
        $this->assertCount(8, $fresh->two_factor_recovery_codes);
        $this->assertDatabaseMissing('users', ['user_id' => $admin->user_id, 'two_factor_secret' => $secret]); // stored encrypted
        $this->assertDatabaseHas('audit_logs', ['action' => 'security.two_factor_enabled']);
        $this->assertDatabaseHas('auth_events', ['user_id' => $admin->user_id, 'event' => 'two_factor_enabled']);

        // First view shows the codes; a reload does not.
        $this->get(route('admin.security.two-factor'))->assertOk()->assertSee('Save your recovery codes');
        $this->get(route('admin.security.two-factor'))->assertOk()->assertDontSee('Save your recovery codes');

        // Setup also marks this session as passed.
        $this->get(route('admin.dashboard'))->assertOk();
    }

    public function test_admin_with_2fa_must_pass_challenge(): void
    {
        [$admin, $secret] = $this->adminWithTwoFactor();

        $this->actingAs($admin)->get(route('admin.dashboard'))
            ->assertRedirect(route('two-factor.challenge'));

        $this->get(route('two-factor.challenge'))->assertOk()->assertSee('Two-step sign-in');

        $this->post(route('two-factor.verify'), ['code' => $this->nextOtp($secret)])
            ->assertRedirect(route('admin.dashboard'));

        $this->get(route('admin.dashboard'))->assertOk();
    }

    public function test_setup_page_is_closed_until_challenge_is_passed(): void
    {
        [$admin, $secret] = $this->adminWithTwoFactor();

        $this->actingAs($admin)->get(route('admin.security.two-factor'))->assertRedirect(route('two-factor.challenge'));
        $this->post(route('admin.security.two-factor.codes'))->assertRedirect(route('two-factor.challenge'));

        // After the challenge the admin lands back where they were headed.
        $this->post(route('two-factor.verify'), ['code' => $this->nextOtp($secret)])
            ->assertRedirect(route('admin.security.two-factor'));
    }

    public function test_json_request_without_2fa_pass_gets_403(): void
    {
        [$admin] = $this->adminWithTwoFactor();

        $this->actingAs($admin)->getJson(route('admin.dashboard'))
            ->assertStatus(403)
            ->assertExactJson(['message' => 'Two-factor required.']);
    }

    public function test_wrong_code_is_refused_and_recorded(): void
    {
        [$admin] = $this->adminWithTwoFactor();

        $this->actingAs($admin)->from(route('two-factor.challenge'))
            ->post(route('two-factor.verify'), ['code' => '123457'])
            ->assertSessionHasErrors('code');

        $this->get(route('admin.dashboard'))->assertRedirect(route('two-factor.challenge'));

        $event = AuthEvent::where('event', 'two_factor_failed')->where('user_id', $admin->user_id)->first();
        $this->assertNotNull($event);
        $this->assertStringNotContainsString('123457', json_encode($event->toArray()));
    }

    public function test_social_style_login_is_still_challenged(): void
    {
        [$admin] = $this->adminWithTwoFactor();

        // Signed in with no password screen and no session flag, like Google/Facebook/webview.
        $this->actingAs($admin)->get(route('admin.users.index'))
            ->assertRedirect(route('two-factor.challenge'));
    }

    public function test_code_cannot_be_replayed(): void
    {
        [$admin, $secret] = $this->adminWithTwoFactor();
        $code = $this->otp($secret);

        // enable() already spent the current step, so the same code is refused straight away.
        $this->actingAs($admin)->post(route('two-factor.verify'), ['code' => $code])
            ->assertSessionHasErrors('code');

        // A code from the next step works once, then not again (new session each time).
        $next = $this->nextOtp($secret);

        $this->flushSession();
        $this->actingAs($admin->fresh())->post(route('two-factor.verify'), ['code' => $next])
            ->assertRedirect(route('admin.dashboard'));

        $this->flushSession();
        $this->actingAs($admin->fresh())->post(route('two-factor.verify'), ['code' => $next])
            ->assertSessionHasErrors('code');
    }

    public function test_recovery_code_works_once(): void
    {
        [$admin, , $codes] = $this->adminWithTwoFactor();

        $this->actingAs($admin)->post(route('two-factor.verify'), ['code' => strtolower($codes[0])])
            ->assertRedirect(route('admin.dashboard'));
        $this->assertCount(7, $admin->fresh()->two_factor_recovery_codes);
        $this->assertDatabaseHas('auth_events', ['user_id' => $admin->user_id, 'event' => 'recovery_code_used']);

        $this->flushSession();
        $this->actingAs($admin->fresh())->post(route('two-factor.verify'), ['code' => $codes[0]])
            ->assertSessionHasErrors('code');

        $this->flushSession();
        $this->actingAs($admin->fresh())->post(route('two-factor.verify'), ['code' => $codes[1]])
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_challenge_is_throttled(): void
    {
        [$admin] = $this->adminWithTwoFactor();

        $this->actingAs($admin);
        for ($i = 0; $i < 5; $i++) {
            $this->post(route('two-factor.verify'), ['code' => '111111'])->assertSessionHasErrors('code');
        }

        $this->post(route('two-factor.verify'), ['code' => '111111'])->assertStatus(429);
    }

    public function test_tenant_and_landlord_unaffected(): void
    {
        $tenant = User::factory()->create();
        $tenant->assignRole('Tenant');
        $landlord = User::factory()->create();
        $landlord->assignRole('Landlord');

        $this->actingAs($tenant)->get(route('properties.index'))->assertOk();
        $this->actingAs($landlord)->get(route('landlord.dashboard'))->assertOk();

        // Admin pages stay a plain 403 for them, not a two-factor redirect.
        $this->actingAs($tenant)->get(route('admin.dashboard'))->assertForbidden();
    }

    public function test_cannot_turn_off_while_required(): void
    {
        [$admin, $secret] = $this->adminWithTwoFactor();
        $this->actingAs($admin)->withSession(['auth.two_factor_passed' => $admin->user_id]);

        $this->delete(route('admin.security.two-factor.destroy'))
            ->assertRedirect(route('admin.security.two-factor'))
            ->assertSessionHas('error');

        $this->assertTrue($admin->fresh()->hasTwoFactor());
        $this->assertSame($secret, $admin->fresh()->two_factor_secret);
    }

    public function test_can_turn_off_when_not_required_and_records_it(): void
    {
        config(['security.admin_two_factor' => 'optional']);
        [$admin] = $this->adminWithTwoFactor();
        $this->actingAs($admin)->withSession(['auth.two_factor_passed' => $admin->user_id]);

        $this->delete(route('admin.security.two-factor.destroy'))->assertRedirect(route('admin.security.two-factor'));

        $fresh = $admin->fresh();
        $this->assertFalse($fresh->hasTwoFactor());
        $this->assertNull($fresh->two_factor_secret);
        $this->assertNull($fresh->two_factor_recovery_codes);
        $this->assertDatabaseHas('audit_logs', ['action' => 'security.two_factor_disabled']);
        $this->assertDatabaseHas('auth_events', ['user_id' => $admin->user_id, 'event' => 'two_factor_disabled']);
    }

    public function test_regenerate_codes_replaces_old_ones_and_is_audited(): void
    {
        [$admin, , $old] = $this->adminWithTwoFactor();
        $this->actingAs($admin)->withSession(['auth.two_factor_passed' => $admin->user_id]);

        $this->post(route('admin.security.two-factor.codes'))
            ->assertRedirect(route('admin.security.two-factor'))
            ->assertSessionHas('two_factor_codes');

        $this->assertDatabaseHas('audit_logs', ['action' => 'security.two_factor_codes']);

        $this->assertFalse(app(TwoFactor::class)->verify($admin->fresh(), $old[0]));
    }

    public function test_mode_off_lets_admin_through(): void
    {
        config(['security.admin_two_factor' => 'off']);
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();
    }

    public function test_secret_and_codes_are_hidden_and_encrypted(): void
    {
        [$admin, $secret] = $this->adminWithTwoFactor();

        $this->assertArrayNotHasKey('two_factor_secret', $admin->toArray());
        $this->assertArrayNotHasKey('two_factor_recovery_codes', $admin->toArray());
        $raw = \DB::table('users')->where('user_id', $admin->user_id)->value('two_factor_secret');
        $this->assertNotSame($secret, $raw);
    }
}
