<?php

namespace Tests\Feature;

use App\Enums\PropertyType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\TestCase;

/**
 * Opens every plain GET page (no URL parameters) as a guest, tenant, landlord
 * and admin and fails on any 5xx. A page may redirect or refuse (302 / 403 /
 * 404 are fine, the role middleware decides that); it must never crash. New
 * routes are picked up automatically from the route table.
 */
class SmokeRoutesTest extends TestCase
{
    use CreatesMarketplaceFixtures;
    use RefreshDatabase;

    /** Not pages: websocket auth, signed / one-time links, logout, framework endpoints. */
    private const SKIP_PREFIXES = ['api', '_', 'up', 'storage', 'sanctum', 'broadcasting', 'verify-email', 'logout', 'livewire'];

    public function test_every_plain_get_page_survives_every_role(): void
    {
        $landlord = $this->makeLandlord();
        $property = $this->makeProperty($landlord, ['title' => 'Smoke Dormitory', 'property_type' => PropertyType::Dormitory->value]);
        $this->makeUnit($property);

        $actors = [
            'guest'    => null,
            'tenant'   => $this->makeTenant(),
            'landlord' => $landlord,
            'admin'    => $this->makeUserWithRole('Admin'),
        ];

        $failures = [];
        $checked = 0;

        foreach ($this->plainGetUris() as $uri) {
            foreach ($actors as $role => $user) {
                $response = ($user ? $this->actingAs($user) : $this)->get('/' . ltrim($uri, '/'));
                $checked++;

                if ($response->getStatusCode() >= 500) {
                    $failures[] = "{$response->getStatusCode()} as {$role}: /{$uri}";
                }
            }

            $this->app['auth']->guard()->forgetUser();
        }

        $this->assertGreaterThan(50, $checked, 'Route discovery found suspiciously few pages.');
        $this->assertSame([], $failures, "Pages that crashed:\n" . implode("\n", $failures));
    }

    public function test_listing_pages_with_a_parameter_render(): void
    {
        $landlord = $this->makeLandlord();
        $property = $this->makeProperty($landlord, ['title' => 'Smoke Dormitory', 'property_type' => PropertyType::Dormitory->value]);
        $this->makeUnit($property);

        $this->get('/properties/' . $property->property_id)->assertOk()->assertSee('Smoke Dormitory');

        foreach (PropertyType::values() as $type) {
            $this->get('/properties?type=' . urlencode($type))->assertOk();
        }

        $this->get('/properties?type=Dormitory')->assertOk()->assertSee('Smoke Dormitory');
    }

    /** @return list<string> */
    private function plainGetUris(): array
    {
        $uris = [];

        foreach (Route::getRoutes() as $route) {
            $uri = $route->uri();

            if (! in_array('GET', $route->methods(), true) || str_contains($uri, '{')) {
                continue;
            }

            foreach (self::SKIP_PREFIXES as $prefix) {
                if ($uri === $prefix || str_starts_with($uri, $prefix . '/')) {
                    continue 2;
                }
            }

            $uris[] = $uri;
        }

        return array_values(array_unique($uris));
    }
}
