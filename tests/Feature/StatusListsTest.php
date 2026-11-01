<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\Support\CreatesPaymentFixtures;
use Tests\TestCase;

/**
 * The reservation lists and their counts are built from Reservation::STATUSES, so Reserved and Completed
 * rows can be filtered to and the tab counts add up to the total.
 */
class StatusListsTest extends TestCase
{
    use CreatesMarketplaceFixtures, CreatesPaymentFixtures, DatabaseTransactions;

    public function test_the_admin_list_filters_to_reserved_and_completed_and_counts_add_up(): void
    {
        $reserved = $this->makeTenancy(['rental_status' => 'Reserved'])['reservation'];
        $completed = $this->makeTenancy(['rental_status' => 'Completed'])['reservation'];
        $admin = $this->makeUserWithRole('Admin');

        foreach (['Reserved' => $reserved, 'Completed' => $completed] as $status => $reservation) {
            $response = $this->actingAs($admin)->get(route('admin.reservations.index', ['status' => $status]))->assertOk();

            $rows = $response->viewData('reservations');
            $this->assertTrue($rows->contains('reservation_id', $reservation->reservation_id));
            $this->assertTrue($rows->every(fn ($r) => $r->rental_status === $status), "only $status rows are listed");
        }

        $counts = $this->actingAs($admin)->get(route('admin.reservations.index'))->viewData('counts');
        $this->assertGreaterThanOrEqual(1, $counts['Reserved']);
        $this->assertGreaterThanOrEqual(1, $counts['Completed']);
        $this->assertSame($counts['all'], array_sum(array_diff_key($counts, ['all' => 1])));
    }

    public function test_the_tenant_list_filters_to_reserved_and_completed_and_counts_add_up(): void
    {
        $tenant = $this->makeTenant();
        $this->makeTenancy(['rental_status' => 'Reserved'], tenant: $tenant);
        $this->makeTenancy(['rental_status' => 'Completed'], tenant: $tenant);
        $this->makeTenancy(['rental_status' => 'Occupied'], tenant: $tenant);

        foreach (['Reserved', 'Completed'] as $status) {
            $rows = $this->actingAs($tenant)->get(route('reservations.index', ['status' => $status]))
                ->assertOk()->viewData('reservations');

            $this->assertCount(1, $rows);
            $this->assertSame($status, $rows->first()->rental_status);
        }

        $counts = $this->actingAs($tenant)->get(route('reservations.index'))->viewData('counts');
        $this->assertSame(3, $counts['all']);
        $this->assertSame(1, $counts['Reserved']);
        $this->assertSame(1, $counts['Completed']);
        $this->assertSame($counts['all'], array_sum(array_diff_key($counts, ['all' => 1])));
    }

    public function test_the_api_tenant_counts_and_filter_include_reserved_and_completed(): void
    {
        $tenant = $this->makeTenant();
        $this->makeTenancy(['rental_status' => 'Reserved'], tenant: $tenant);
        $this->makeTenancy(['rental_status' => 'Completed'], tenant: $tenant);

        Sanctum::actingAs($tenant);

        $this->getJson('/api/v1/tenant/reservations')
            ->assertOk()
            ->assertJsonPath('counts.all', 2)
            ->assertJsonPath('counts.Reserved', 1)
            ->assertJsonPath('counts.Completed', 1);

        $this->getJson('/api/v1/tenant/reservations?status=Completed')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.rental_status', 'Completed');
    }

    public function test_the_api_agreement_view_is_open_to_a_completed_tenancy(): void
    {
        $t = $this->makeTenancy(['rental_status' => 'Completed']);

        Sanctum::actingAs($t['tenant']);

        $this->getJson("/api/v1/tenant/reservations/{$t['reservation']->reservation_id}/agreement")
            ->assertOk()
            ->assertJsonPath('needs_tenant_details', true);
    }
}
