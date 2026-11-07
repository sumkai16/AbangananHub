<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\TestCase;

/**
 * Every list page that reads search/filter input, hit as its own role with SQL-injection text and
 * with arrays where a string is expected. Passing means: no 5xx, and no SLEEP() ever ran (a page
 * that obeyed `SLEEP(2)` would take 2+ seconds). Values are bound everywhere, so the real risk
 * is a crash from the wrong type, which a hand-edited URL can cause.
 */
class HostileSearchInputTest extends TestCase
{
    use CreatesMarketplaceFixtures, DatabaseTransactions;

    private const PARAMS = [
        'search', 'status', 'role', 'type', 'property_type', 'sort', 'health', 'filter', 'box', 'concern',
        'verification_status', 'availability_status', 'visibility', 'rating', 'action', 'section', 'time',
        'range', 'view', 'month', 'tab', 'starter', 'offset', 'from', 'to', 'property', 'property_id',
        'min_fee', 'max_fee', 'page', 'per_page',
    ];

    private const PAGES = [
        'Admin' => ['admin/audit-logs', 'admin/catalogue/properties', 'admin/catalogue/properties/export', 'admin/catalogue/units',
            'admin/catalogue/units/export', 'admin/conversations', 'admin/dashboard', 'admin/deposit-charges', 'admin/documents',
            'admin/listings/approval', 'admin/payments', 'admin/payouts', 'admin/ratings', 'admin/report-analytics',
            'admin/report-analytics/export', 'admin/reports', 'admin/reservations', 'admin/reviews', 'admin/units', 'admin/users',
            'admin/verifications'],
        'Landlord' => ['landlord/analytics', 'landlord/analytics/export', 'landlord/complaints', 'landlord/contracts', 'landlord/dashboard',
            'landlord/leases', 'landlord/payments', 'landlord/payments/export', 'landlord/payouts', 'landlord/properties',
            'landlord/reservations', 'landlord/reservations/export', 'landlord/reservations/viewings', 'landlord/reviews',
            'landlord/tenants', 'landlord/tenants/export', 'landlord/units', 'landlord/units/export', 'conversations',
            'notifications', 'notifications/recent', 'conversations/recent-messages'],
        'Tenant' => ['tenant/reports', 'tenant/reservations', 'favorites', 'conversations', 'notifications', 'tenant/profile'],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        config(['broadcasting.default' => 'null']);
    }

    /** @return array<string, array{string, string, string}> */
    public static function cases(): array
    {
        $cases = [];
        foreach (self::PAGES as $role => $paths) {
            foreach ($paths as $path) {
                foreach (['string' => "x' OR '1'='1' AND SLEEP(3) -- ", 'array' => ['x', ['y' => 'z']], 'long' => str_repeat('%_\\', 400)] as $kind => $value) {
                    $cases["$role $path ($kind)"] = [$role, $path, $kind];
                }
            }
        }

        return $cases;
    }

    #[DataProvider('cases')]
    public function test_page_survives_hostile_input(string $role, string $path, string $kind): void
    {
        $value = match ($kind) {
            'string' => "x' OR '1'='1' AND SLEEP(3) -- ",
            'array'  => ['x', ['y' => 'z']],
            'long'   => str_repeat('%_\\', 400),
        };

        $this->actingAs($this->makeUserWithRole($role));

        $started = microtime(true);
        $response = $this->get('/' . $path . '?' . http_build_query(array_fill_keys(self::PARAMS, $value)));
        $took = microtime(true) - $started;

        $this->assertLessThan(500, $response->getStatusCode(), "$role /$path returned {$response->getStatusCode()} for $kind input");
        $this->assertLessThan(2.5, $took, "/$path took {$took}s: did SLEEP() run?");
    }
}
