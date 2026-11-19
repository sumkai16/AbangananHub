<?php

namespace Tests\Feature\Security;

use App\Models\AuditLog;
use App\Models\AuthEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityEventsPageTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(string $role, array $attrs = []): User
    {
        $user = User::factory()->create($attrs);
        $user->assignRole($role);

        return $user;
    }

    private function event(?User $user, string $event, array $attrs = []): AuthEvent
    {
        return AuthEvent::create(array_merge([
            'user_id' => $user?->user_id,
            'email' => $user?->email,
            'event' => $event,
            'channel' => 'web',
            'ip_address' => '203.0.113.5',
            'user_agent' => 'Mozilla/5.0 (Linux; Android 14) Chrome/120.0 Mobile Safari/537.36',
        ], $attrs));
    }

    public function test_admin_sees_security_events(): void
    {
        $admin = $this->userWithRole('Admin');
        $tenant = $this->userWithRole('Tenant', ['first_name' => 'Marisol', 'last_name' => 'Dela Cruz']);
        $this->event($tenant, 'login');
        $this->event(null, 'login_failed', ['email' => 'ghost@example.test']);

        $this->actingAs($admin)->get(route('admin.security-events.index'))
            ->assertOk()
            ->assertSee('Marisol Dela Cruz')
            ->assertSee('Signed in')
            ->assertSee('ghost@example.test')
            ->assertSee('Failed sign-in')
            ->assertSee('Chrome on Android')
            ->assertSee('203.0.113.5');
    }

    public function test_filters_by_role_and_event(): void
    {
        $admin = $this->userWithRole('Admin');
        $tenant = $this->userWithRole('Tenant', ['first_name' => 'Tessa', 'last_name' => 'Tenantson']);
        $landlord = $this->userWithRole('Landlord', ['first_name' => 'Lando', 'last_name' => 'Landlordson']);
        $this->event($tenant, 'login');
        $this->event($landlord, 'login_failed');

        $this->actingAs($admin)->get(route('admin.security-events.index', ['role' => 'Landlord', 'event' => 'login_failed']))
            ->assertOk()
            ->assertSee('Lando Landlordson')
            ->assertDontSee('Tessa Tenantson');

        $this->actingAs($admin)->get(route('admin.security-events.index', ['event' => 'login']))
            ->assertOk()
            ->assertSee('Tessa Tenantson')
            ->assertDontSee('Lando Landlordson');

        // Unknown role / event values are ignored, not an error.
        $this->actingAs($admin)->get(route('admin.security-events.index', ['role' => 'Hacker', 'event' => 'nope']))
            ->assertOk()
            ->assertSee('Tessa Tenantson')
            ->assertSee('Lando Landlordson');
    }

    public function test_search_matches_typed_email_and_user_name(): void
    {
        $admin = $this->userWithRole('Admin');
        $tenant = $this->userWithRole('Tenant', ['first_name' => 'Zebediah', 'last_name' => 'Quark']);
        $this->event($tenant, 'login');
        $this->event(null, 'login_failed', ['email' => 'stranger@example.test']);

        $this->actingAs($admin)->get(route('admin.security-events.index', ['search' => 'Zebediah']))
            ->assertOk()->assertSee('Zebediah Quark')->assertDontSee('stranger@example.test');
        $this->actingAs($admin)->get(route('admin.security-events.index', ['search' => 'stranger@']))
            ->assertOk()->assertSee('stranger@example.test')->assertDontSee('Zebediah Quark');
    }

    public function test_non_admin_gets_403_and_guest_is_redirected(): void
    {
        $this->actingAs($this->userWithRole('Tenant'))->get(route('admin.security-events.index'))->assertForbidden();
        $this->actingAs($this->userWithRole('Landlord'))->get(route('admin.security-events.index'))->assertForbidden();

        auth()->logout();
        $this->get(route('admin.security-events.index'))->assertRedirect();
    }

    public function test_audit_log_role_filter(): void
    {
        $admin = $this->userWithRole('Admin', ['first_name' => 'Adela', 'last_name' => 'Adminova']);
        $landlord = $this->userWithRole('Landlord', ['first_name' => 'Lando', 'last_name' => 'Landlordson']);

        $this->actingAs($admin);
        AuditLog::record('user.update', 'Admin entry marker');
        $this->actingAs($landlord);
        AuditLog::record('payment.void', 'Landlord entry marker');

        $this->actingAs($admin)->get(route('admin.audit-logs.index', ['role' => 'Landlord']))
            ->assertOk()
            ->assertSee('Landlord entry marker')
            ->assertDontSee('Admin entry marker');

        $this->actingAs($admin)->get(route('admin.audit-logs.index', ['role' => 'Admin']))
            ->assertOk()
            ->assertSee('Admin entry marker')
            ->assertDontSee('Landlord entry marker');

        $this->actingAs($admin)->get(route('admin.audit-logs.index'))
            ->assertOk()
            ->assertSee('Admin entry marker')
            ->assertSee('Landlord entry marker');
    }

    public function test_tabs_are_present_on_both_pages(): void
    {
        $admin = $this->userWithRole('Admin');

        foreach (['admin.audit-logs.index', 'admin.security-events.index'] as $route) {
            $this->actingAs($admin)->get(route($route))
                ->assertOk()
                ->assertSee('Admin actions')
                ->assertSee('Account &amp; security', false)
                ->assertSee(route('admin.audit-logs.index'), false)
                ->assertSee(route('admin.security-events.index'), false)
                ->assertSee('Activity Logs');
        }
    }
}
