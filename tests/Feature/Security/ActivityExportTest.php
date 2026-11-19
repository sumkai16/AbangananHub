<?php

namespace Tests\Feature\Security;

use App\Models\AuditLog;
use App\Models\AuthEvent;
use App\Models\User;
use App\Support\CsvExport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityExportTest extends TestCase
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

    private function auditRow(array $attrs = []): AuditLog
    {
        return AuditLog::create(array_merge([
            'actor_name' => 'Ada Admin',
            'actor_email' => 'ada@example.test',
            'action' => 'user.suspended',
            'summary' => 'Suspended a user.',
            'ip_address' => '198.51.100.7',
        ], $attrs));
    }

    public function test_export_respects_filters(): void
    {
        $admin = $this->userWithRole('Admin');
        $tenant = $this->userWithRole('Tenant', ['first_name' => 'Tessa', 'last_name' => 'Tenantson']);
        $landlord = $this->userWithRole('Landlord', ['first_name' => 'Lando', 'last_name' => 'Landlordson']);
        $this->event($tenant, 'login');
        $this->event($landlord, 'login_failed');

        $response = $this->actingAs($admin)
            ->get(route('admin.security-events.export', ['role' => 'Landlord', 'event' => 'login_failed']))
            ->assertOk();
        $csv = $response->streamedContent();

        $this->assertStringContainsString('Lando Landlordson', $csv);
        $this->assertStringContainsString('Failed sign-in', $csv);
        $this->assertStringContainsString('Chrome on Android', $csv);
        $this->assertStringNotContainsString('Tessa Tenantson', $csv);
        $this->assertStringNotContainsString('Signed in', $csv);
    }

    public function test_audit_export_has_headers_and_rows(): void
    {
        $admin = $this->userWithRole('Admin');
        $this->auditRow(['reason' => 'Chargeback']);

        $csv = $this->actingAs($admin)->get(route('admin.audit-logs.export'))->assertOk()->streamedContent();
        $lines = array_map('str_getcsv', array_filter(explode("\n", ltrim($csv, "\xEF\xBB\xBF"))));
        $lines = array_values($lines);

        $this->assertSame(['When', 'Actor', 'Actor email', 'Action', 'Summary', 'Reason', 'IP'], $lines[0]);
        $row = $lines[1];
        $this->assertSame('Ada Admin', $row[1]);
        $this->assertSame('ada@example.test', $row[2]);
        $this->assertSame('Suspended a user.', $row[4]);
        $this->assertSame('Chargeback', $row[5]);
        $this->assertSame('198.51.100.7', $row[6]);
        $this->assertCount(2, $lines);
    }

    public function test_export_escapes_formula_cells(): void
    {
        $admin = $this->userWithRole('Admin');
        $this->auditRow(['actor_name' => '=HYPERLINK("x")', 'summary' => '+cmd']);
        $evil = $this->userWithRole('Tenant', ['first_name' => '@SUM(1)', 'last_name' => 'Evil']);
        $this->event($evil, 'login');
        $this->event(null, 'login_failed', ['email' => '-2+3@example.test']);

        $audit = $this->actingAs($admin)->get(route('admin.audit-logs.export'))->streamedContent();
        $this->assertStringContainsString('"\'=HYPERLINK(""x"")"', $audit);
        $this->assertStringContainsString("'+cmd", $audit);

        $sec = $this->actingAs($admin)->get(route('admin.security-events.export'))->streamedContent();
        $this->assertStringContainsString("'@SUM(1) Evil", $sec);
        $this->assertStringContainsString("'-2+3@example.test", $sec);

        $this->assertSame("'=1", CsvExport::safe('=1'));
        $this->assertSame("'\tx", CsvExport::safe("\tx"));
        $this->assertSame("'\rx", CsvExport::safe("\rx"));
        $this->assertSame('plain', CsvExport::safe('plain'));
        $this->assertSame('', CsvExport::safe(null));
    }

    public function test_export_has_bom_and_content_disposition(): void
    {
        $admin = $this->userWithRole('Admin');
        $this->event($admin, 'login');

        foreach ([['admin.audit-logs.export', 'admin-actions-'], ['admin.security-events.export', 'account-security-']] as [$route, $prefix]) {
            $response = $this->actingAs($admin)->get(route($route))->assertOk();

            $this->assertStringStartsWith("\xEF\xBB\xBF", $response->streamedContent());
            $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
            $this->assertStringContainsString('charset=UTF-8', $response->headers->get('Content-Type'));
            $this->assertStringContainsString('attachment', $response->headers->get('Content-Disposition'));
            $this->assertStringContainsString($prefix . now(config('rentals.timezone'))->format('Y-m-d') . '.csv', $response->headers->get('Content-Disposition'));
            $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        }
    }

    public function test_export_is_audited(): void
    {
        $admin = $this->userWithRole('Admin');
        $this->event($admin, 'login');
        $this->event($admin, 'logout');

        $this->actingAs($admin)->get(route('admin.security-events.export', ['event' => 'login']))->assertOk();

        $row = AuditLog::where('action', 'activity.export')->firstOrFail();
        $this->assertSame($admin->user_id, $row->actor_id);
        $this->assertStringContainsString('Exported 1 rows', $row->summary);
        $this->assertStringContainsString('account & security', $row->summary);
        $this->assertSame('login', $row->metadata['filters']['event']);
        $this->assertSame(1, $row->metadata['rows']);

        $this->actingAs($admin)->get(route('admin.audit-logs.export'))->assertOk();
        $this->assertSame(2, AuditLog::where('action', 'activity.export')->count());
    }

    public function test_audit_export_does_not_include_its_own_export_row(): void
    {
        $admin = $this->userWithRole('Admin');
        $this->auditRow();

        $csv = $this->actingAs($admin)->get(route('admin.audit-logs.export'))->streamedContent();

        $this->assertStringNotContainsString('Activity log exported', $csv);
        $this->assertStringContainsString('Exported 1 rows of the admin actions log.', AuditLog::where('action', 'activity.export')->value('summary'));
    }

    public function test_non_admin_gets_403_and_guest_redirected(): void
    {
        $tenant = $this->userWithRole('Tenant');
        $routes = ['admin.audit-logs.export', 'admin.security-events.export'];

        // Guests first: actingAs() keeps the user signed in for the rest of the test.
        foreach ($routes as $route) {
            $this->get(route($route))->assertRedirect(route('login'));
        }
        foreach ($routes as $route) {
            $this->actingAs($tenant)->get(route($route))->assertForbidden();
        }

        $this->assertSame(0, AuditLog::where('action', 'activity.export')->count());
    }

    public function test_export_stops_at_cap(): void
    {
        config(['security.export_row_cap' => 3]);
        $admin = $this->userWithRole('Admin');
        foreach (range(1, 5) as $i) {
            $this->event(null, 'login_failed', ['email' => "bot{$i}@example.test"]);
        }

        $csv = $this->actingAs($admin)->get(route('admin.security-events.export'))->streamedContent();

        $this->assertSame(1 + 3, count(array_filter(explode("\n", ltrim($csv, "\xEF\xBB\xBF")))));
        // Newest first, so the cap keeps the most recent rows.
        $this->assertStringContainsString('bot5@example.test', $csv);
        $this->assertStringNotContainsString('bot1@example.test', $csv);
        $this->assertSame(3, AuditLog::where('action', 'activity.export')->firstOrFail()->metadata['rows']);
    }
}
