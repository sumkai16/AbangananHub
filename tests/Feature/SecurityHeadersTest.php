<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\TestCase;

/**
 * The headers that tell a browser not to guess file types, not to be framed by other sites and not to leak the
 * full address in the Referer. They ride on every response: pages, JSON and downloads.
 */
class SecurityHeadersTest extends TestCase
{
    use CreatesMarketplaceFixtures, DatabaseTransactions;

    public function test_pages_the_api_and_errors_all_carry_the_safety_headers(): void
    {
        $this->actingAs($this->makeTenant());

        foreach ([$this->get('/properties'), $this->getJson('/api/v1/properties'), $this->get('/no-such-page'), $this->get('/landlord/dashboard')] as $response) {
            $response->assertHeader('X-Content-Type-Options', 'nosniff')
                ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
                ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        }
    }

    public function test_hsts_is_only_sent_over_https_in_production(): void
    {
        $this->get('/properties')->assertHeaderMissing('Strict-Transport-Security');

        $this->app['env'] = 'production';
        $this->get('https://localhost/properties')->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        $this->get('http://localhost/properties')->assertHeaderMissing('Strict-Transport-Security');
    }
}
