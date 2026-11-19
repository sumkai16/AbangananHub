<?php

namespace Tests\Feature;

use App\Models\Report;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\Support\CreatesPaymentFixtures;
use Tests\TestCase;

/** Reviews, Reports and Conversations share the Reservations filter bar: stat strip, autosubmit dropdowns, no Filter button. */
class AdminFilterBarsInboxTest extends TestCase
{
    use CreatesMarketplaceFixtures, CreatesPaymentFixtures, DatabaseTransactions;

    public function test_the_three_pages_load_with_the_new_bar_and_no_filter_button(): void
    {
        $t = $this->makeTenancy(['rental_status' => 'Occupied']);
        $admin = $this->makeUserWithRole('Admin');

        foreach (['admin.reviews.index', 'admin.reports.index', 'admin.conversations.index'] as $route) {
            $this->actingAs($admin)->get(route($route, ['search' => 'zzz-nothing']))->assertOk()->assertDontSee('>Filter</button>', false);
        }

        $this->actingAs($admin)->get(route('admin.reviews.index', ['sort' => 'lowest', 'visibility' => 'hidden']))->assertOk()->assertSee('Low rated');
        $this->actingAs($admin)->get(route('admin.conversations.index', ['sort' => 'oldest', 'status' => 'Open']))->assertOk()->assertSee('Total conversations');
    }

    public function test_reports_search_finds_by_reason_and_the_status_counts_follow_it(): void
    {
        $t = $this->makeTenancy(['rental_status' => 'Occupied']);
        $admin = $this->makeUserWithRole('Admin');
        Report::create(['reporter_id' => $t['tenant']->user_id, 'property_id' => $t['reservation']->property_id, 'report_reason' => 'Zebra-striped fake photos', 'report_status' => 'Pending']);

        $this->actingAs($admin)->get(route('admin.reports.index', ['search' => 'Zebra-striped']))->assertOk()->assertSee('Zebra-striped fake photos');
        $this->actingAs($admin)->get(route('admin.reports.index', ['search' => 'no-such-reason-xyz']))->assertOk()->assertDontSee('Zebra-striped fake photos');
    }
}
