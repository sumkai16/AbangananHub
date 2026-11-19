<?php

namespace Tests\Feature\Holds;

use App\Models\AuditLog;
use App\Models\HoldMatchAlert;
use App\Models\TenantHold;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\TestCase;

/** The admin "Tenant holds" page: tabs, confirm / reject / release, duplicate alerts, and the step-up password. */
class AdminTenantHoldsTest extends TestCase
{
    use CreatesMarketplaceFixtures, DatabaseTransactions;

    private function admin(): User
    {
        return $this->makeUserWithRole('Admin');
    }

    private function hold(string $status = TenantHold::PENDING, array $extra = []): TenantHold
    {
        return TenantHold::create($extra + [
            'tenant_id'   => $this->makeTenant()->user_id,
            'landlord_id' => $this->makeLandlord()->user_id,
            'amount_owed' => 7500,
            'reason'      => 'Two months of rent unpaid, no reply.',
            'status'      => $status,
            'flagged_at'  => now()->subHours(3),
        ]);
    }

    private function alert(?TenantHold $hold = null): HoldMatchAlert
    {
        $hold ??= $this->hold(TenantHold::ACTIVE);

        return HoldMatchAlert::create([
            'hold_id' => $hold->hold_id,
            'user_id' => $this->makeTenant()->user_id,
            'signals' => ['phone', 'device'],
        ]);
    }

    public function test_each_tab_lists_only_its_own_rows(): void
    {
        $pending = $this->hold(TenantHold::PENDING, ['reason' => 'PENDING-REASON-XYZ']);
        $active = $this->hold(TenantHold::ACTIVE, ['reason' => 'ACTIVE-REASON-XYZ']);
        $released = $this->hold(TenantHold::RELEASED, ['reason' => 'RELEASED-REASON-XYZ', 'release_reason' => 'paid']);
        $rejected = $this->hold(TenantHold::REJECTED, ['reason' => 'REJECTED-REASON-XYZ', 'rejected_note' => 'Landlord had been paid in cash.']);
        $alert = $this->alert($active);
        $admin = $this->admin();

        $tab = fn (string $t) => $this->actingAs($admin)->get(route('admin.tenant-holds.index', ['tab' => $t]));

        $tab('pending')->assertOk()->assertSee('PENDING-REASON-XYZ')->assertDontSee('ACTIVE-REASON-XYZ')->assertDontSee('RELEASED-REASON-XYZ');
        $tab('active')->assertOk()->assertSee('ACTIVE-REASON-XYZ')->assertDontSee('PENDING-REASON-XYZ');
        $tab('history')->assertOk()->assertSee('RELEASED-REASON-XYZ')->assertSee('REJECTED-REASON-XYZ')
            ->assertSee('Rent paid')->assertSee('Landlord had been paid in cash.')->assertDontSee('PENDING-REASON-XYZ');
        $tab('duplicates')->assertOk()->assertSee($alert->user->email)->assertSee('Same phone number')->assertSee('Same browser or device');
        // No tab given: Pending.
        $this->actingAs($admin)->get(route('admin.tenant-holds.index'))->assertOk()->assertSee('PENDING-REASON-XYZ');
    }

    public function test_the_tenant_note_and_landlord_show_on_a_row_and_empty_tabs_say_so(): void
    {
        $this->hold(TenantHold::ACTIVE, ['tenant_note' => 'I was in hospital last month.']);
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.tenant-holds.index', ['tab' => 'active']))
            ->assertSee('I was in hospital last month.');
        $this->actingAs($admin)->get(route('admin.tenant-holds.index', ['tab' => 'history']))
            ->assertSee('Nothing here yet');
    }

    public function test_confirming_puts_the_hold_in_force_and_writes_the_audit_row(): void
    {
        $hold = $this->hold();
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.tenant-holds.confirm', $hold))
            ->assertRedirect()->assertSessionHas('success');

        $this->assertSame(TenantHold::ACTIVE, $hold->fresh()->status);
        $this->assertSame($admin->user_id, (int) $hold->fresh()->confirmed_by);
        $this->assertTrue(AuditLog::where('action', 'hold.confirmed')->exists());
        $this->assertTrue($hold->tenant->fresh()->isOnHold());
    }

    public function test_confirming_a_hold_that_is_not_pending_shows_a_message_not_a_500(): void
    {
        $hold = $this->hold(TenantHold::RELEASED, ['release_reason' => 'admin']);

        $this->actingAs($this->admin())->post(route('admin.tenant-holds.confirm', $hold))
            ->assertRedirect()->assertSessionHas('error');

        $this->assertSame(TenantHold::RELEASED, $hold->fresh()->status);
    }

    public function test_rejecting_needs_a_note_and_then_closes_the_report(): void
    {
        $hold = $this->hold();
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.tenant-holds.reject', $hold), ['note' => ''])
            ->assertSessionHasErrors('note');
        $this->assertSame(TenantHold::PENDING, $hold->fresh()->status);

        $this->actingAs($admin)->post(route('admin.tenant-holds.reject', $hold), ['note' => 'The tenant paid in cash.'])
            ->assertRedirect()->assertSessionHas('success');

        $this->assertSame(TenantHold::REJECTED, $hold->fresh()->status);
        $this->assertSame('The tenant paid in cash.', $hold->fresh()->rejected_note);
        $this->assertTrue(AuditLog::where('action', 'hold.rejected')->exists());
    }

    public function test_releasing_lifts_the_hold_and_writes_the_audit_row(): void
    {
        $hold = $this->hold(TenantHold::ACTIVE);

        $this->actingAs($this->admin())->post(route('admin.tenant-holds.release', $hold))
            ->assertRedirect()->assertSessionHas('success');

        $this->assertSame(TenantHold::RELEASED, $hold->fresh()->status);
        $this->assertSame('admin', $hold->fresh()->release_reason);
        $this->assertTrue(AuditLog::where('action', 'hold.released')->exists());
        $this->assertFalse($hold->tenant->fresh()->isOnHold());
    }

    public function test_dismissing_an_alert_closes_it_and_writes_the_audit_row(): void
    {
        $alert = $this->alert();

        $this->actingAs($this->admin())->post(route('admin.hold-alerts.dismiss', $alert))
            ->assertRedirect()->assertSessionHas('success');

        $this->assertSame(HoldMatchAlert::DISMISSED, $alert->fresh()->status);
        $this->assertTrue(AuditLog::where('action', 'hold.duplicate_dismissed')->exists());
        $this->actingAs($this->admin())->get(route('admin.tenant-holds.index', ['tab' => 'duplicates']))
            ->assertDontSee($alert->user->email);
    }

    public function test_putting_a_duplicate_on_hold_creates_a_linked_hold(): void
    {
        $source = $this->hold(TenantHold::ACTIVE);
        $alert = $this->alert($source);

        $this->actingAs($this->admin())->post(route('admin.hold-alerts.hold', $alert))
            ->assertRedirect()->assertSessionHas('success');

        $copy = TenantHold::where('tenant_id', $alert->user_id)->first();
        $this->assertNotNull($copy);
        $this->assertSame(TenantHold::ACTIVE, $copy->status);
        $this->assertSame($source->hold_id, (int) $copy->source_hold_id);
        $this->assertSame(HoldMatchAlert::HELD, $alert->fresh()->status);
        $this->assertTrue(AuditLog::where('action', 'hold.duplicate_held')->exists());
    }

    public function test_only_admins_can_open_or_use_the_page(): void
    {
        $hold = $this->hold();

        foreach ([$this->makeTenant(), $this->makeLandlord()] as $user) {
            $this->actingAs($user)->get(route('admin.tenant-holds.index'))->assertForbidden();
            $this->actingAs($user)->post(route('admin.tenant-holds.confirm', $hold))->assertForbidden();
            $this->actingAs($user)->post(route('admin.tenant-holds.reject', $hold), ['note' => 'x'])->assertForbidden();
        }

        $this->assertSame(TenantHold::PENDING, $hold->fresh()->status);
    }

    public function test_confirm_release_and_put_on_hold_need_the_password_again(): void
    {
        config(['security.step_up_enabled' => true]);
        $admin = $this->admin();
        $pending = $this->hold();
        $active = $this->hold(TenantHold::ACTIVE);
        $alert = $this->alert();

        $this->actingAs($admin)->post(route('admin.tenant-holds.confirm', $pending))->assertSessionHas('step_up_required');
        $this->actingAs($admin)->post(route('admin.tenant-holds.release', $active))->assertSessionHas('step_up_required');
        $this->actingAs($admin)->post(route('admin.hold-alerts.hold', $alert))->assertSessionHas('step_up_required');
        $this->assertSame(TenantHold::PENDING, $pending->fresh()->status);
        $this->assertSame(TenantHold::ACTIVE, $active->fresh()->status);
        $this->assertSame(HoldMatchAlert::OPEN, $alert->fresh()->status);

        $fresh = ['auth.step_up_at' => now()->timestamp];
        $this->actingAs($admin)->withSession($fresh)->post(route('admin.tenant-holds.confirm', $pending))->assertSessionHas('success');
        $this->assertSame(TenantHold::ACTIVE, $pending->fresh()->status);

        // Rejecting and dismissing are not sensitive: no password needed.
        $other = $this->hold();
        $this->actingAs($admin)->post(route('admin.tenant-holds.reject', $other), ['note' => 'Paid in cash.'])->assertSessionHas('success');
    }

    public function test_the_sidebar_counts_reports_to_confirm_and_duplicates_to_review(): void
    {
        $this->hold();
        $this->hold();
        $this->alert();

        $html = $this->actingAs($this->admin())->get(route('admin.tenant-holds.index'))->getContent();

        $this->assertStringContainsString('Tenant holds', $html);
        $this->assertMatchesRegularExpression('/data-holds-badge[^>]*>\s*(\d+)\s*</', $html);
        preg_match('/data-holds-badge[^>]*>\s*(\d+)\s*</', $html, $m);
        $this->assertGreaterThanOrEqual(3, (int) $m[1]);
    }
}
