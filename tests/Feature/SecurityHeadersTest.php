<?php

namespace Tests\Feature;

use App\Support\ContentSecurityPolicy;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/** Browser-side hardening headers: CSP (off / report-only / enforce), HSTS on https, Permissions-Policy. */
class SecurityHeadersTest extends TestCase
{
    public function test_report_only_mode_sends_the_report_header_and_not_the_enforcing_one(): void
    {
        config(['security.csp_mode' => 'report-only']);

        $response = $this->get('/about');

        $response->assertHeader('Content-Security-Policy-Report-Only');
        $response->assertHeaderMissing('Content-Security-Policy');
        $policy = $response->headers->get('Content-Security-Policy-Report-Only');
        $this->assertStringContainsString("frame-ancestors 'self'", $policy);
        $this->assertStringContainsString("object-src 'none'", $policy);
        $this->assertStringContainsString("base-uri 'self'", $policy);
        $this->assertStringContainsString('report-uri /csp-report', $policy);
    }

    public function test_enforce_mode_sends_the_enforcing_header_only(): void
    {
        config(['security.csp_mode' => 'enforce']);

        $response = $this->get('/about');

        $response->assertHeader('Content-Security-Policy');
        $response->assertHeaderMissing('Content-Security-Policy-Report-Only');
    }

    public function test_off_mode_sends_no_csp(): void
    {
        config(['security.csp_mode' => 'off']);

        $response = $this->get('/about');

        $response->assertHeaderMissing('Content-Security-Policy');
        $response->assertHeaderMissing('Content-Security-Policy-Report-Only');
    }

    public function test_camera_and_location_stay_on_this_site_and_nothing_else_is_asked_for(): void
    {
        $permissions = $this->get('/about')->headers->get('Permissions-Policy');

        $this->assertStringContainsString('camera=(self)', $permissions);
        $this->assertStringContainsString('geolocation=(self)', $permissions);
        $this->assertStringContainsString('microphone=()', $permissions);
        $this->get('/about')->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function test_hsts_is_sent_on_https_only(): void
    {
        $this->get('http://localhost/about')->assertHeaderMissing('Strict-Transport-Security');

        $this->get('https://localhost/about')->assertHeader('Strict-Transport-Security', 'max-age=15552000');

        config(['security.hsts_max_age' => 0]);
        $this->get('https://localhost/about')->assertHeaderMissing('Strict-Transport-Security');
    }

    public function test_the_policy_names_the_live_chat_websocket(): void
    {
        config(['security.ws' => ['host' => 'chat.example.test', 'port' => 443, 'scheme' => 'https']]);
        $this->assertStringContainsString('wss://chat.example.test', ContentSecurityPolicy::build());

        config(['security.ws' => ['host' => '127.0.0.1', 'port' => 8080, 'scheme' => 'http']]);
        $this->assertStringContainsString('ws://127.0.0.1:8080', ContentSecurityPolicy::build());
        $this->assertStringContainsString('wss://127.0.0.1:8080', ContentSecurityPolicy::build());
    }

    public function test_a_violation_report_is_logged_short_and_answered_with_204(): void
    {
        Log::shouldReceive('warning')->once()->withArgs(function ($message, $context) {
            return $message === 'CSP violation'
                && $context['directive'] === 'img-src'
                && $context['blocked'] === 'http://evil.test/x.png'
                && strlen($context['page']) === 200;
        });

        $this->call('POST', '/csp-report', [], [], [], ['CONTENT_TYPE' => 'application/csp-report'], json_encode(['csp-report' => [
            'violated-directive' => 'img-src',
            'blocked-uri' => 'http://evil.test/x.png',
            'document-uri' => str_repeat('a', 500),
        ]]))->assertNoContent();
    }

    public function test_garbage_sent_to_the_report_endpoint_is_ignored(): void
    {
        Log::shouldReceive('warning')->never();

        $this->call('POST', '/csp-report', [], [], [], ['CONTENT_TYPE' => 'application/csp-report'], 'not json at all')->assertNoContent();
    }
}
