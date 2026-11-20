<?php

namespace Tests\Feature\Security;

use App\Models\AuditLog;
use App\Models\User;
use App\Models\UserActivity;
use App\Support\ActivityCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/** The all-users activity log: LogUserActivity writes a row for every state-changing request, on every route. */
class UserActivityLogTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(string $role, array $attrs = []): User
    {
        $user = User::factory()->create($attrs);
        $user->assignRole($role);

        return $user;
    }

    private function profilePayload(User $user, array $over = []): array
    {
        return array_merge(['first_name' => $user->first_name, 'last_name' => $user->last_name, 'email' => $user->email], $over);
    }

    public function test_a_tenants_change_is_logged_with_who_what_and_where(): void
    {
        $tenant = $this->userWithRole('Tenant', ['first_name' => 'Marisol', 'last_name' => 'Dela Cruz']);

        $this->actingAs($tenant)->from(route('profile.edit'))
            ->patch(route('profile.update'), $this->profilePayload($tenant));

        $row = UserActivity::sole();
        $this->assertSame($tenant->user_id, $row->user_id);
        $this->assertSame('Marisol Dela Cruz', $row->actor_name);
        $this->assertSame($tenant->email, $row->actor_email);
        $this->assertSame('Tenant', $row->actor_role);
        $this->assertSame('profile.update', $row->action);
        $this->assertSame('Updated their profile', $row->summary);
        $this->assertSame('PATCH', $row->method);
        $this->assertSame('/profile', $row->path);
        $this->assertSame('web', $row->channel);
        $this->assertSame('ok', $row->outcome);
    }

    public function test_the_record_acted_on_is_kept(): void
    {
        $admin = $this->userWithRole('Admin');
        $target = $this->userWithRole('Tenant', ['account_status' => 'active']);

        $this->actingAs($admin)->from('/admin/users')
            ->patch(route('admin.users.updateStatus', $target), ['status' => 'suspended']);

        $row = UserActivity::where('action', 'admin.users.updateStatus')->sole();
        $this->assertSame('Admin', $row->actor_role);
        $this->assertSame('User', $row->target_type);
        $this->assertSame($target->user_id, $row->target_id);
        $this->assertSame('User #' . $target->user_id, $row->targetLabel());
        // The route pattern is stored, never the real URL.
        $this->assertSame('/admin/users/{user}/status', $row->path);
    }

    public function test_a_person_who_is_admin_and_landlord_is_logged_as_admin(): void
    {
        $both = $this->userWithRole('Landlord');
        $both->assignRole('Admin');

        $this->actingAs($both)->from(route('profile.edit'))
            ->patch(route('profile.update'), $this->profilePayload($both));

        $this->assertSame('Admin', UserActivity::sole()->actor_role);
    }

    public function test_a_form_sent_back_with_errors_is_logged_as_failed(): void
    {
        $tenant = $this->userWithRole('Tenant');

        $this->actingAs($tenant)->from(route('profile.edit'))
            ->patch(route('profile.update'), $this->profilePayload($tenant, ['first_name' => '']))
            ->assertSessionHasErrors('first_name');

        $this->assertSame('failed', UserActivity::sole()->outcome);
    }

    public function test_a_refused_request_is_logged_as_denied(): void
    {
        $tenant = $this->userWithRole('Tenant');

        $this->actingAs($tenant)->patch(route('admin.settings.update'), [])->assertForbidden();

        $row = UserActivity::where('action', 'admin.settings.update')->sole();
        $this->assertSame('denied', $row->outcome);
        $this->assertSame(403, $row->status);
        $this->assertSame('Tenant', $row->actor_role);
    }

    public function test_viewing_a_page_is_not_an_action(): void
    {
        $tenant = $this->userWithRole('Tenant');

        $this->actingAs($tenant)->get(route('profile.edit'))->assertOk();

        $this->assertSame(0, UserActivity::count());
    }

    public function test_a_download_is_an_action(): void
    {
        $admin = $this->userWithRole('Admin');

        $this->actingAs($admin)->get(route('admin.user-activity.export'))->assertOk();

        $row = UserActivity::where('action', 'admin.user-activity.export')->sole();
        $this->assertSame('Exported the activity log', $row->summary);
        $this->assertSame('GET', $row->method);
    }

    public function test_pings_and_previews_are_not_logged(): void
    {
        $tenant = $this->userWithRole('Tenant');

        $this->actingAs($tenant)->post(route('notifications.readAll'));

        $this->assertSame(0, UserActivity::count());
    }

    public function test_requests_from_nobody_signed_in_are_not_logged(): void
    {
        $user = $this->userWithRole('Tenant');

        $this->post('/login', ['email' => $user->email, 'password' => 'wrong-password']);

        $this->assertSame(0, UserActivity::count());
    }

    public function test_signing_in_and_out_are_logged(): void
    {
        $user = $this->userWithRole('Tenant');

        $this->post('/login', ['email' => $user->email, 'password' => 'password']);
        $this->assertAuthenticatedAs($user);
        $this->post('/logout');
        $this->assertGuest();

        $this->assertSame(['Signed in', 'Signed out'], UserActivity::orderBy('activity_id')->pluck('summary')->all());
        $this->assertSame([$user->user_id, $user->user_id], UserActivity::orderBy('activity_id')->pluck('user_id')->all());
    }

    public function test_the_request_body_is_never_stored(): void
    {
        $user = $this->userWithRole('Tenant');

        $this->actingAs($user)->from(route('profile.edit'))
            ->put(route('password.update'), [
                'current_password' => 'password',
                'password' => 'Brand-new-secret-77',
                'password_confirmation' => 'Brand-new-secret-77',
            ]);

        $row = UserActivity::sole();
        $this->assertStringNotContainsString('Brand-new-secret-77', json_encode($row->getAttributes()));
        $this->assertStringNotContainsString('password":"password', json_encode($row->getAttributes()));
    }

    public function test_the_row_survives_the_account_being_deleted(): void
    {
        $tenant = $this->userWithRole('Tenant', ['first_name' => 'Marisol', 'last_name' => 'Dela Cruz']);
        $this->actingAs($tenant)->from(route('profile.edit'))->patch(route('profile.update'), $this->profilePayload($tenant));

        $tenant->delete();

        $row = UserActivity::sole();
        $this->assertNull($row->user_id);
        $this->assertSame('Marisol Dela Cruz', $row->actor_name);
    }

    public function test_old_rows_are_pruned_and_recent_ones_kept(): void
    {
        config(['security.user_activity_retention_days' => 365]);
        $old = UserActivity::create(['action' => 'a', 'summary' => 's', 'method' => 'POST', 'path' => '/a', 'status' => 200]);
        $old->forceFill(['created_at' => now()->subDays(366)])->save();
        $recent = UserActivity::create(['action' => 'b', 'summary' => 's', 'method' => 'POST', 'path' => '/b', 'status' => 200]);

        $this->artisan('model:prune', ['--model' => [UserActivity::class]]);

        $this->assertDatabaseMissing('user_activities', ['activity_id' => $old->activity_id]);
        $this->assertDatabaseHas('user_activities', ['activity_id' => $recent->activity_id]);
    }

    // ----- The catalogue stays in step with the routes -----

    public function test_every_worded_route_name_is_a_real_route(): void
    {
        $names = collect(Route::getRoutes()->getRoutes())->map->getName()->filter()->all();

        $missing = array_values(array_diff(
            array_merge(array_keys(ActivityCatalog::LABELS), ActivityCatalog::GET_ACTIONS, ActivityCatalog::IGNORED_ROUTES),
            $names,
            // Registered only in debug or by a package, so they may not exist in the test app.
            ['storage.local.upload', 'webhooks.paymongo'],
        ));

        $this->assertSame([], $missing, 'ActivityCatalog names routes that do not exist: ' . implode(', ', $missing));
    }

    public function test_every_worded_api_path_is_a_real_unnamed_route(): void
    {
        $paths = [];
        foreach (Route::getRoutes()->getRoutes() as $route) {
            if ($route->getName()) {
                continue;
            }
            foreach ($route->methods() as $method) {
                $paths[] = $method . ' ' . ActivityCatalog::pathKey($route);
            }
        }

        $missing = array_values(array_diff(array_keys(ActivityCatalog::PATH_LABELS), $paths));

        $this->assertSame([], $missing, 'ActivityCatalog words API routes that do not exist: ' . implode(', ', $missing));
    }

    public function test_every_loggable_route_has_a_sentence(): void
    {
        foreach (Route::getRoutes()->getRoutes() as $route) {
            foreach (array_diff($route->methods(), ['HEAD', 'OPTIONS']) as $method) {
                if (! ActivityCatalog::shouldLog($route, $method)) {
                    continue;
                }

                $summary = ActivityCatalog::summary($route, $method);
                $this->assertNotSame('', trim($summary), "{$method} {$route->uri()} has no sentence");
                $this->assertNotSame('', ActivityCatalog::actionKey($route, $method));
            }
        }
    }

    public function test_an_unworded_route_still_reads_as_a_sentence(): void
    {
        $route = Route::post('/landlord/gadget-boxes/{box}/open-lid', fn () => 'ok')->name('landlord.gadget-boxes.openLid');

        $this->assertSame('Did gadget boxes open lid', ActivityCatalog::summary($route, 'POST'));

        $store = Route::post('/landlord/widgets', fn () => 'ok')->name('landlord.widgets.store');
        $this->assertSame('Created widgets', ActivityCatalog::summary($store, 'POST'));
    }

    // ----- The admin page -----

    public function test_admin_sees_the_activity_log(): void
    {
        $admin = $this->userWithRole('Admin');
        $tenant = $this->userWithRole('Tenant', ['first_name' => 'Marisol', 'last_name' => 'Dela Cruz']);
        $this->actingAs($tenant)->from(route('profile.edit'))->patch(route('profile.update'), $this->profilePayload($tenant));
        auth()->logout();

        $this->actingAs($admin)->get(route('admin.user-activity.index'))
            ->assertOk()
            ->assertSee('All user activity')
            ->assertSee('Marisol Dela Cruz')
            ->assertSee('Updated their profile')
            ->assertSee('PATCH /profile');
    }

    public function test_a_tenant_cannot_open_the_activity_log(): void
    {
        $tenant = $this->userWithRole('Tenant');

        $this->actingAs($tenant)->get(route('admin.user-activity.index'))->assertForbidden();
        $this->actingAs($tenant)->get(route('admin.user-activity.export'))->assertForbidden();
    }

    public function test_filters_narrow_the_list(): void
    {
        $admin = $this->userWithRole('Admin');
        UserActivity::create(['actor_name' => 'Tess Tenant', 'actor_role' => 'Tenant', 'action' => 'a', 'summary' => 'Sent a message', 'method' => 'POST', 'path' => '/a', 'status' => 200, 'outcome' => 'ok']);
        UserActivity::create(['actor_name' => 'Lando Landlord', 'actor_role' => 'Landlord', 'action' => 'b', 'summary' => 'Voided a payment', 'method' => 'POST', 'path' => '/b', 'status' => 422, 'outcome' => 'failed']);

        $this->actingAs($admin)->get(route('admin.user-activity.index', ['role' => 'Landlord']))
            ->assertSee('Voided a payment')->assertDontSee('Sent a message');

        $this->actingAs($admin)->get(route('admin.user-activity.index', ['outcome' => 'ok']))
            ->assertSee('Sent a message')->assertDontSee('Voided a payment');

        $this->actingAs($admin)->get(route('admin.user-activity.index', ['search' => 'Lando']))
            ->assertSee('Voided a payment')->assertDontSee('Sent a message');
    }

    public function test_the_export_matches_the_filters_and_is_audited(): void
    {
        $admin = $this->userWithRole('Admin');
        UserActivity::create(['actor_name' => 'Tess Tenant', 'actor_role' => 'Tenant', 'action' => 'a', 'summary' => 'Sent a message', 'method' => 'POST', 'path' => '/a', 'status' => 200, 'outcome' => 'ok']);
        UserActivity::create(['actor_name' => 'Lando Landlord', 'actor_role' => 'Landlord', 'action' => 'b', 'summary' => 'Voided a payment', 'method' => 'POST', 'path' => '/b', 'status' => 200, 'outcome' => 'ok']);

        $csv = $this->actingAs($admin)->get(route('admin.user-activity.export', ['role' => 'Tenant']))
            ->assertOk()->streamedContent();

        $this->assertStringContainsString('Sent a message', $csv);
        $this->assertStringNotContainsString('Voided a payment', $csv);
        $this->assertSame(1, AuditLog::where('action', 'activity.export')->count());
    }
}
