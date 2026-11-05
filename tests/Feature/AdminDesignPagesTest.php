<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\LandlordVerification;
use App\Models\Report;
use App\Models\Reservation;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\Support\CreatesPaymentFixtures;
use Tests\TestCase;

/**
 * Every admin page was rebuilt on the same shared pieces (x-admin.*, x-stat-strip). These load each page with real
 * rows and with none, and check the dashboard's queue, so a markup or variable slip surfaces here rather than in
 * front of the administrator.
 */
class AdminDesignPagesTest extends TestCase
{
    use CreatesMarketplaceFixtures, CreatesPaymentFixtures, DatabaseTransactions;

    /** @return array<string, array{string, array<string, string>}> */
    public static function adminPages(): array
    {
        return [
            'dashboard' => ['admin.dashboard', []],
            'users' => ['admin.users.index', []],
            'landlords' => ['admin.users.index', ['role' => 'Landlord']],
            'verifications' => ['admin.verifications.index', ['status' => 'All']],
            'property verifications' => ['admin.listings.approval', ['status' => 'All']],
            'documents' => ['admin.documents.index', ['status' => 'All']],
            'unit approvals' => ['admin.units.index', ['status' => 'All']],
            'properties' => ['admin.catalogue.properties.index', []],
            'units' => ['admin.catalogue.units.index', []],
            'reservations' => ['admin.reservations.index', []],
            'disputed reservations' => ['admin.reservations.index', ['filter' => 'disputed']],
            'payments' => ['admin.payments.index', ['status' => 'All']],
            'payouts' => ['admin.payouts.index', []],
            'deposit charges' => ['admin.deposit-charges.index', ['status' => 'All']],
            'reviews' => ['admin.reviews.index', []],
            'reports' => ['admin.reports.index', ['status' => 'All']],
            'conversations' => ['admin.conversations.index', []],
            'audit logs' => ['admin.audit-logs.index', []],
            'ratings' => ['admin.ratings.index', []],
            'analytics properties' => ['admin.report-analytics.index', ['section' => 'properties']],
            'analytics reservations' => ['admin.report-analytics.index', ['section' => 'reservations']],
            'analytics users' => ['admin.report-analytics.index', ['section' => 'users']],
            'settings' => ['admin.settings.edit', []],
            'profile' => ['admin.profile.edit', []],
        ];
    }

    /** @dataProvider adminPages */
    public function test_each_admin_page_loads_with_rows(string $route, array $query): void
    {
        $t = $this->makeTenancy(['rental_status' => 'Occupied']);
        $this->makePayment($t['reservation'], ['status' => 'Held', 'amount' => 5000]);
        Report::create(['reporter_id' => $t['tenant']->user_id, 'property_id' => $t['reservation']->property_id, 'report_reason' => 'Fake listing', 'report_status' => 'Pending']);

        $this->actingAs($this->makeUserWithRole('Admin'))->get(route($route, $query))->assertOk();
    }

    public function test_detail_pages_load(): void
    {
        $admin = $this->makeUserWithRole('Admin');
        $t = $this->makeTenancy(['rental_status' => 'Occupied']);
        $this->makePayment($t['reservation'], ['status' => 'Held', 'amount' => 5000]);
        $report = Report::create(['reporter_id' => $t['tenant']->user_id, 'property_id' => $t['reservation']->property_id, 'report_reason' => 'Fake listing', 'report_status' => 'Pending']);
        $property = $t['reservation']->property;

        $this->actingAs($admin);
        $this->get(route('admin.reservations.show', $t['reservation']))->assertOk();
        $this->get(route('admin.users.show', $t['tenant']))->assertOk();
        $this->get(route('admin.users.show', $t['landlord']))->assertOk();
        $this->get(route('admin.reports.show', $report))->assertOk();
        $this->get(route('admin.catalogue.properties.show', $property))->assertOk();
        $this->get(route('admin.units.show', [$property, $t['reservation']->unit]))->assertOk();
    }

    /** @dataProvider reservationStates */
    public function test_the_reservation_page_handles_every_state(string $status, string $expected): void
    {
        $t = $this->makeTenancy(['rental_status' => $status, 'rejection_reason' => $status === 'Rejected' ? 'Unit no longer available' : null]);

        $this->actingAs($this->makeUserWithRole('Admin'))
            ->get(route('admin.reservations.show', $t['reservation']))
            ->assertOk()
            ->assertSee($expected);
    }

    /** @return array<string, array{string, string}> */
    public static function reservationStates(): array
    {
        return [
            'inquiry' => ['Inquiry', 'Admin actions'],
            'reserved' => ['Reserved', 'waiting for move-in'],
            'occupied' => ['Occupied', 'Agreement signed'],
            'completed' => ['Completed', 'The tenancy has ended'],
            'cancelled' => ['Cancelled', 'was cancelled'],
            'rejected' => ['Rejected', 'Unit no longer available'],
        ];
    }

    public function test_the_unit_page_lists_its_reservations_and_the_other_units(): void
    {
        $t = $this->makeTenancy(['rental_status' => 'Occupied']);
        $property = $t['reservation']->property;
        $other = $this->makeUnit($property, ['unit_label' => 'Sibling Room']);

        $this->actingAs($this->makeUserWithRole('Admin'))
            ->get(route('admin.units.show', [$property, $t['reservation']->unit]))
            ->assertOk()
            ->assertSee('Reservations for this unit')
            ->assertSee(route('admin.reservations.show', $t['reservation']), false)
            ->assertSee($other->unit_label);
    }

    public function test_a_pending_property_can_be_decided_from_its_page(): void
    {
        $property = $this->makeProperty($this->makeLandlord(), ['verification_status' => 'Pending']);

        $this->actingAs($this->makeUserWithRole('Admin'))
            ->get(route('admin.catalogue.properties.show', $property))
            ->assertOk()
            ->assertSee(route('admin.listings.approve', $property->property_id), false)
            ->assertSee(route('admin.listings.reject', $property->property_id), false);
    }

    public function test_payment_rows_link_to_their_reservation(): void
    {
        $t = $this->makeTenancy(['rental_status' => 'Occupied']);
        $this->makePayment($t['reservation'], ['status' => 'Held', 'amount' => 5000]);

        $this->actingAs($this->makeUserWithRole('Admin'))
            ->get(route('admin.payments.index', ['status' => 'Held']))
            ->assertOk()
            ->assertSee(route('admin.reservations.show', $t['reservation']), false)
            ->assertSee('Showing');
    }

    public function test_the_conversation_page_names_the_unit(): void
    {
        $t = $this->makeTenancy(['rental_status' => 'Occupied']);
        $unit = $t['reservation']->unit;
        $conversation = Conversation::create([
            'tenant_id' => $t['tenant']->user_id,
            'landlord_id' => $t['landlord']->user_id,
            'property_id' => $t['reservation']->property_id,
            'unit_id' => $unit->unit_id,
        ]);

        $this->actingAs($this->makeUserWithRole('Admin'))
            ->get(route('admin.conversations.show', $conversation))
            ->assertOk()
            ->assertSee($unit->unit_label);
    }

    public function test_the_queue_counts_what_waits_and_reports_the_oldest(): void
    {
        Carbon::setTestNow('2026-11-05 10:00:00');
        $admin = $this->makeUserWithRole('Admin');

        $baseline = $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()->viewData('queue');
        $before = collect($baseline)->keyBy('label');

        $landlord = $this->makeLandlord();
        $this->makeProperty($landlord, ['verification_status' => 'Pending', 'created_at' => now()->subDays(40)]);
        $t = $this->makeTenancy(['rental_status' => 'Rental Agreement Signed', 'move_in_disputed_at' => now()->subDays(50)]);

        $response = $this->get(route('admin.dashboard'))->assertOk();
        $queue = collect($response->viewData('queue'))->keyBy('label');

        $this->assertSame($before['Property verifications']['count'] + 1, $queue['Property verifications']['count']);
        $this->assertSame($before['Move-in disputes']['count'] + 1, $queue['Move-in disputes']['count']);
        $this->assertSame(collect($queue)->sum('count'), $response->viewData('openItems'));
        $this->assertTrue($queue['Move-in disputes']['oldest']->lte(now()->subDays(50)));
        $response->assertSee('Needs you')->assertDontSee('Welcome back');
    }

    public function test_the_dashboard_says_so_when_nothing_is_waiting_and_money_totals_follow_the_payments(): void
    {
        $t = $this->makeTenancy(['rental_status' => 'Occupied']);
        $this->makePayment($t['reservation'], ['status' => 'Held', 'amount' => 1234.50]);

        $response = $this->actingAs($this->makeUserWithRole('Admin'))->get(route('admin.dashboard'))->assertOk();

        $this->assertGreaterThanOrEqual(1234.50, $response->viewData('moneyHeld'));
        if ($response->viewData('openItems') === 0) {
            $response->assertSee('Nothing waiting');
        }
    }

    public function test_the_reservation_ledger_counts_every_status_and_its_shares_add_up(): void
    {
        $this->makeTenancy(['rental_status' => 'Occupied']);
        $this->makeTenancy(['rental_status' => 'Reserved']);
        $this->makeTenancy(['rental_status' => 'Inquiry']);

        $response = $this->actingAs($this->makeUserWithRole('Admin'))->get(route('admin.dashboard'))->assertOk();
        $ledger = $response->viewData('reservationLedger');

        $this->assertSame(Reservation::count(), $ledger['total']);
        $this->assertSame(100, array_sum(array_column($ledger['rows'], 'share')));
        $response->assertSee('Quick actions')->assertSee('Review landlords')->assertSee('Manage users')->assertSee('In progress');
    }

    public function test_the_page_titles_match_the_sidebar(): void
    {
        $this->actingAs($this->makeUserWithRole('Admin'));

        $this->get(route('admin.report-analytics.index'))->assertSeeInOrder(['<h1', 'Analytics'], false);
        $this->get(route('admin.payments.index'))->assertSeeInOrder(['<h1', 'Payments'], false)->assertDontSee('Payment Management');
        $this->get(route('admin.users.index', ['role' => 'Tenant']))->assertSeeInOrder(['<h1', 'Tenants'], false)->assertDontSee('System Users');
    }

    public function test_verification_review_shows_id_and_selfie_whole(): void
    {
        $verification = LandlordVerification::create([
            'user_id' => $this->makeTenant()->user_id, 'government_id' => 'x', 'id_image_hash' => str_repeat('c', 64),
            'id_type' => 'Driver License', 'selfie' => 'x', 'business_name' => 'Test', 'contact_number' => '0917',
            'business_address' => 'Somewhere', 'verification_status' => 'Pending', 'submitted_at' => now(),
            'face_match_status' => 'match', 'face_match_score' => 91.25,
        ]);

        $this->actingAs($this->makeUserWithRole('Admin'))
            ->get(route('admin.verifications.show', $verification))
            ->assertOk()
            ->assertSee('object-contain', false)
            ->assertDontSee('object-cover', false)
            ->assertSeeInOrder(['91.25%', 'ID front', 'Selfie'], false);
    }

    public function test_risky_admin_actions_ask_first(): void
    {
        $admin = $this->makeUserWithRole('Admin');
        $landlord = $this->makeLandlord();
        $this->makeProperty($landlord, ['verification_status' => 'Pending']);

        $this->actingAs($admin)->get(route('admin.users.show', $landlord))->assertOk()->assertSee('Suspend this account?');
        $this->actingAs($admin)->get(route('admin.listings.approval', ['status' => 'Pending']))->assertOk()->assertSee('Approve this listing?');
    }

    public function test_a_status_keeps_one_colour_and_unit_statuses_keep_their_own(): void
    {
        $general = $this->blade('<x-admin.status status="Occupied" />');
        $unit = $this->blade('<x-admin.status status="Occupied" kind="unit" />');

        $general->assertSee('bg-[#FF8A66] text-white', false);
        $unit->assertSee('text-[#DC2626]', false);
        $this->blade('<x-admin.status status="Rejected" />')->assertSee('text-[#DC2626]', false);
        $this->blade('<x-admin.status status="Whatever" />')->assertSee('text-[#5B6A8E]', false);
        $this->blade('<x-admin.status status="Refund issued" tone="red" />')->assertSee('text-[#DC2626]', false);
    }

    public function test_the_users_page_filters_by_role_and_status_and_shows_what_needs_attention(): void
    {
        $admin = $this->makeUserWithRole('Admin');
        $landlord = $this->makeLandlord();
        $landlord->update(['account_status' => 'suspended']);

        $all = $this->actingAs($admin)->get(route('admin.users.index'))->assertOk()->assertSee('Needs attention')->assertSee('New sign-ups');
        $this->assertGreaterThanOrEqual(1, $all->viewData('attention')['suspended']);
        $this->assertCount(30, $all->viewData('signupData'));

        $suspended = $this->get(route('admin.users.index', ['status' => 'suspended']))->assertOk()->viewData('users');
        $this->assertTrue($suspended->every(fn ($u) => $u->account_status === 'suspended'));
        $this->assertTrue($suspended->contains('user_id', $landlord->user_id));

        $this->get(route('admin.users.index', ['role' => 'No role']))->assertOk();
        $this->get(route('admin.users.index', ['role' => 'Landlord', 'status' => 'active', 'search' => 'x']))->assertOk();
    }
}
