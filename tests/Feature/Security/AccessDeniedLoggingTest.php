<?php

namespace Tests\Feature\Security;

use App\Models\AuthEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AccessDeniedLoggingTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_hitting_admin_route_is_recorded(): void
    {
        $tenant = User::factory()->create();
        $tenant->assignRole('Tenant');

        $this->actingAs($tenant)->get('/admin/users')->assertForbidden();

        $this->assertSame(1, AuthEvent::where('event', 'access_denied')->count());
        $event = AuthEvent::where('event', 'access_denied')->first();
        $this->assertSame($tenant->user_id, $event->user_id);
        $this->assertSame('admin/users', $event->metadata['path']);
        $this->assertSame('GET', $event->metadata['method']);
    }

    public function test_anonymous_public_403_is_not_recorded(): void
    {
        Route::middleware('web')->get('/__public-forbidden', fn () => abort(403));

        $this->get('/__public-forbidden')->assertForbidden();

        $this->assertSame(0, AuthEvent::count());
    }

    public function test_signed_in_user_denied_on_public_path_is_recorded(): void
    {
        Route::middleware('web')->get('/__public-forbidden', fn () => abort(403));

        $this->actingAs(User::factory()->create())->get('/__public-forbidden')->assertForbidden();

        $this->assertSame(1, AuthEvent::where('event', 'access_denied')->count());
    }

    public function test_normal_pages_record_nothing(): void
    {
        $this->get('/')->assertOk();
        $this->get('/does-not-exist')->assertNotFound();

        $this->assertSame(0, AuthEvent::count());
    }
}
