<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Notification;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\Support\CreatesPaymentFixtures;
use Tests\TestCase;

/**
 * The tenant-side safety fixes: input that must never reach a <script>, who a report form may list, money an
 * account deletion must not erase, and the notification and reservation-list dead ends that hid real state.
 */
class TenantSafetyTest extends TestCase
{
    use CreatesMarketplaceFixtures, CreatesPaymentFixtures, DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        config(['broadcasting.default' => 'null']);
        Carbon::setTestNow('2026-10-14 10:00:00');
    }

    // ─── reflected XSS in the inbox script ──────────────────────

    public function test_the_inbox_script_never_echoes_the_active_query_parameter(): void
    {
        $tenant = $this->makeTenant();

        $html = $this->actingAs($tenant)->get('/conversations?active=alert(document.cookie)')->assertOk()->getContent();

        $this->assertStringContainsString('const active = null;', $html);
        $this->assertStringNotContainsString('alert(document.cookie)', $html);
    }

    public function test_the_inbox_script_still_opens_a_numeric_conversation(): void
    {
        $tenant = $this->makeTenant();

        $html = $this->actingAs($tenant)->get('/conversations?active=7')->assertOk()->getContent();

        $this->assertStringContainsString('const active = 7;', $html);
    }

    // ─── report form: no directory of every user's email ────────

    public function test_the_report_form_lists_only_people_you_have_messaged_and_never_emails(): void
    {
        $tenant = $this->makeTenant();
        $partner = $this->makeLandlord();
        $stranger = $this->makeLandlord();
        $partner->update(['first_name' => 'Partnerella', 'last_name' => 'Known', 'email' => 'partner-private@example.test']);
        $stranger->update(['first_name' => 'Strangerino', 'last_name' => 'Unknown', 'email' => 'stranger-private@example.test']);

        $unit = $this->makeUnit($this->makeProperty($partner));
        Conversation::create([
            'tenant_id' => $tenant->user_id, 'landlord_id' => $partner->user_id,
            'property_id' => $unit->property_id, 'unit_id' => $unit->unit_id,
        ]);

        $html = $this->actingAs($tenant)->get(route('reports.create'))->assertOk()->getContent();

        $this->assertStringContainsString('Partnerella Known', $html);
        $this->assertStringNotContainsString('Strangerino', $html);
        $this->assertStringNotContainsString('partner-private@example.test', $html);
        $this->assertStringNotContainsString('stranger-private@example.test', $html);
    }

    // ─── account deletion must not erase unsettled money ────────

    private function deleteAccount(User $user)
    {
        return $this->actingAs($user)->delete(route('profile.destroy'), ['password' => 'password']);
    }

    public function test_an_account_with_a_held_payment_on_a_cancelled_tenancy_cannot_be_deleted(): void
    {
        $t = $this->makeTenancy(['rental_status' => 'Cancelled']);
        $this->makePayment($t['reservation'], ['payment_type' => 'Initial', 'billing_period' => null, 'status' => 'Held', 'payment_method' => 'GCash']);

        $this->deleteAccount($t['tenant'])->assertSessionHas('error', fn ($m) => str_contains($m, 'held by AbangananHub'));

        $this->assertNotNull(User::find($t['tenant']->user_id), 'the account survives');
        $this->assertSame(1, Payment::where('reservation_id', $t['reservation']->reservation_id)->count(), 'and so does the payment');
    }

    public function test_an_account_with_rent_still_waiting_to_be_paid_out_cannot_be_deleted(): void
    {
        $t = $this->makeTenancy(['rental_status' => 'Completed']);
        $this->makePayment($t['reservation'], ['status' => 'Paid', 'payout_status' => 'Pending Payout', 'payment_method' => 'GCash']);

        $this->deleteAccount($t['tenant'])->assertSessionHas('error');

        $this->assertNotNull(User::find($t['tenant']->user_id));
    }

    public function test_a_clean_account_is_still_deleted(): void
    {
        $tenant = $this->makeTenant();

        $this->deleteAccount($tenant)->assertRedirect('/');

        $this->assertNull(User::find($tenant->user_id));
    }

    // ─── notifications ──────────────────────────────────────────

    public function test_the_reservations_tab_shows_tenancy_notifications(): void
    {
        $tenant = $this->makeTenant();
        Notification::notify($tenant->user_id, 'payment', 'Rent receipt marker', 'You paid.', route('reservations.index'));
        Notification::notify($tenant->user_id, 'listing', 'Listing news marker', 'Something else.');

        $html = $this->actingAs($tenant)->get(route('notifications.index', ['tab' => 'reservation']))->assertOk()->getContent();

        $this->assertStringContainsString('Rent receipt marker', $html);
        $this->assertStringNotContainsString('Listing news marker', $html);
    }

    public function test_a_notification_with_a_link_gets_an_open_button_on_the_notifications_page(): void
    {
        $tenant = $this->makeTenant();
        // A destination nothing else on the page links to, so this can only pass if the Open button renders.
        $target = url('/tenant/unique-open-target-123');
        $n = Notification::notify($tenant->user_id, 'payment', 'Rent due', 'Please pay.', $target);

        $html = $this->actingAs($tenant)->get(route('notifications.index', ['selected' => $n->notification_id]))->assertOk()->getContent();

        $this->assertStringContainsString('href="'.$target.'"', $html);
        $this->assertStringNotContainsString("doesn't have any linked details", $html);
    }

    // ─── reservations list ──────────────────────────────────────

    public function test_the_reservations_list_shows_the_move_in_date_not_the_inquiry_date(): void
    {
        $t = $this->makeTenancy([
            'rental_status' => 'Under Negotiation',
            'reservation_date' => '2026-10-14',
            'target_move_in_date' => '2026-11-20',
        ]);

        $html = $this->actingAs($t['tenant'])->get(route('reservations.index'))->assertOk()->getContent();

        $this->assertStringContainsString('Move-in: Nov 20, 2026', $html);
        $this->assertStringNotContainsString('Move-in: Oct 14, 2026', $html);
    }

    public function test_the_reservations_list_says_flexible_when_no_move_in_date_was_given(): void
    {
        $t = $this->makeTenancy(['rental_status' => 'Under Negotiation', 'target_move_in_date' => null]);

        $html = $this->actingAs($t['tenant'])->get(route('reservations.index'))->assertOk()->getContent();

        $this->assertStringContainsString('Move-in: Flexible', $html);
    }

    public function test_an_agreement_waiting_to_be_signed_gets_a_review_and_sign_button(): void
    {
        $t = $this->makeTenancy(['rental_status' => 'Pending Rental Agreement']);

        $html = $this->actingAs($t['tenant'])->get(route('reservations.index'))->assertOk()->getContent();

        $this->assertStringContainsString(route('agreements.show', $t['reservation']), $html);
        $this->assertStringContainsString('Review &amp; sign', $html);
    }

    public function test_inquiry_cancel_asks_for_confirmation(): void
    {
        $t = $this->makeTenancy(['rental_status' => 'Inquiry']);

        $html = $this->actingAs($t['tenant'])->get(route('reservations.index'))->assertOk()->getContent();

        $this->assertStringContainsString('data-confirm="Cancel this inquiry?"', $html);
    }

    public function test_a_completed_tenancy_links_to_its_tenancy_page(): void
    {
        $t = $this->makeTenancy(['rental_status' => 'Completed']);

        $html = $this->actingAs($t['tenant'])->get(route('reservations.index'))->assertOk()->getContent();

        $this->assertStringContainsString(route('tenancy.show', $t['reservation']), $html);
    }

    public function test_the_account_menu_links_a_tenant_to_my_reports(): void
    {
        $tenant = $this->makeTenant();

        $html = $this->actingAs($tenant)->get(route('reservations.index'))->assertOk()->getContent();

        $this->assertStringContainsString(route('tenant.reports.index'), $html);
    }
}
