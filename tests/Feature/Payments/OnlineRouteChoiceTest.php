<?php

namespace Tests\Feature\Payments;

use App\Support\Payments;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\Support\CreatesPaymentFixtures;
use Tests\TestCase;

/**
 * Online payment sits next to direct payment: with the platform switch on, the tenant picks per payment, but only
 * when this landlord can be paid online (GCash details on file, no opt-out). Pinned here:
 *   - who is offered the choice, and who only ever sees the direct route;
 *   - the landlord's opt-out on Account Settings (web and API), which only changes when the request carries it;
 *   - the rent page and the "next step" button follow the same answer.
 * The checkout refusals themselves live in TenantCheckoutTest.
 */
class OnlineRouteChoiceTest extends TestCase
{
    use CreatesMarketplaceFixtures, CreatesPaymentFixtures, DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        config(['rentals.online_payments_enabled' => true]);
        Carbon::setTestNow('2026-10-14 10:00:00');
    }

    // ─── who is offered online ──────────────────────────────────

    public function test_online_is_offered_only_with_the_switch_on_gcash_on_file_and_no_opt_out(): void
    {
        $t = $this->makeTenancy();
        $reservation = $t['reservation']->fresh('property');

        $this->assertFalse(Payments::onlineFor($reservation), 'No GCash details yet.');

        $t['landlord']->savePayoutAccount('09171234567', 'Test Landlord');
        $this->assertTrue(Payments::onlineFor($reservation));

        $t['landlord']->setAcceptsOnlinePayments(false);
        $this->assertFalse(Payments::onlineFor($reservation), 'Opted out.');

        $t['landlord']->setAcceptsOnlinePayments(true);
        $this->assertTrue(Payments::onlineFor($reservation));

        config(['rentals.online_payments_enabled' => false]);
        $this->assertFalse(Payments::onlineFor($reservation), 'The admin switch wins over everything.');
    }

    public function test_one_gcash_field_alone_is_not_enough(): void
    {
        $t = $this->makeTenancy();
        $t['landlord']->savePayoutAccount('09171234567', null);

        $this->assertFalse($t['landlord']->acceptsOnlinePayments());
    }

    public function test_clearing_the_gcash_details_takes_online_away(): void
    {
        $t = $this->makeOnlineTenancy();
        $this->assertTrue($t['landlord']->acceptsOnlinePayments());

        $t['landlord']->savePayoutAccount(null, null);

        $this->assertFalse($t['landlord']->acceptsOnlinePayments());
    }

    // ─── the landlord's opt-out ─────────────────────────────────

    public function test_account_settings_show_the_toggle_and_it_saves(): void
    {
        $landlord = $this->makeLandlord();
        $this->actingAs($landlord)->get(route('profile.edit'))->assertOk()->assertSee('Let tenants pay me online');

        $this->patch(route('landlord.profile.update'), [
            'gcash_number' => '09171112222', 'gcash_account_name' => 'Maria Santos', 'accepts_online' => '0',
        ])->assertRedirect(route('profile.edit'));
        $this->assertFalse($landlord->fresh()->acceptsOnlinePayments());
        $this->assertFalse($landlord->fresh()->payoutAccount->accepts_online);

        $this->patch(route('landlord.profile.update'), [
            'gcash_number' => '09171112222', 'gcash_account_name' => 'Maria Santos', 'accepts_online' => '1',
        ]);
        $this->assertTrue($landlord->fresh()->acceptsOnlinePayments());
    }

    public function test_a_save_that_leaves_the_choice_out_keeps_it(): void
    {
        $landlord = $this->makeLandlord();
        $landlord->savePayoutAccount('09171112222', 'Maria Santos');
        $landlord->setAcceptsOnlinePayments(false);

        $this->actingAs($landlord)->patch(route('landlord.profile.update'), ['business_name' => 'Santos Rentals']);

        $this->assertFalse($landlord->fresh()->payoutAccount->accepts_online);
    }

    public function test_the_choice_is_not_a_boolean_garbage_value(): void
    {
        $landlord = $this->makeLandlord();
        $landlord->savePayoutAccount('09171112222', 'Maria Santos');

        $this->actingAs($landlord)->patch(route('landlord.profile.update'), ['accepts_online' => 'banana'])
            ->assertSessionHasErrors('accepts_online');
    }

    public function test_the_api_saves_the_choice_and_returns_it(): void
    {
        $landlord = $this->makeLandlord();
        $landlord->savePayoutAccount('09171112222', 'Maria Santos');
        Sanctum::actingAs($landlord, ['*']);

        $this->patchJson('/api/v1/landlord/profile/me', [
            'first_name' => $landlord->first_name, 'last_name' => $landlord->last_name, 'accepts_online' => false,
        ])->assertOk();

        $this->assertFalse($landlord->fresh()->acceptsOnlinePayments());
        // The landlord's own profile (which carries the payout details) reports it back.
        $this->getJson('/api/v1/profile')->assertOk()->assertJsonPath('data.user.accepts_online', false);
    }

    // ─── what the tenant sees ───────────────────────────────────

    public function test_the_rent_page_offers_the_choice_when_the_landlord_can_be_paid_online(): void
    {
        $t = $this->makeOnlineTenancy();

        $this->actingAs($t['tenant'])->get(route('tenancy.show', $t['reservation']))
            ->assertOk()->assertSee('How do you want to pay')->assertSee('Pay online')->assertSee('Pay cash')->assertSee('Pay your landlord:');
    }

    public function test_the_rent_page_shows_only_the_direct_route_otherwise(): void
    {
        $t = $this->makeTenancy();

        $this->actingAs($t['tenant'])->get(route('tenancy.show', $t['reservation']))
            ->assertOk()->assertDontSee('Pay online')->assertSee('Pay cash')->assertSee('Pay your landlord:');
    }

    public function test_the_next_step_button_follows_the_same_answer(): void
    {
        $online = $this->makeOnlineTenancy(['rental_status' => 'Rental Agreement Signed']);
        $direct = $this->makeTenancy(['rental_status' => 'Rental Agreement Signed']);

        $this->assertSame('Pay now', $online['reservation']->fresh('property')->tenantNextStep()['label']);
        $this->assertSame('Pay your landlord', $direct['reservation']->fresh('property')->tenantNextStep()['label']);
    }

    public function test_the_cash_option_says_what_to_do_and_the_amount(): void
    {
        $t = $this->makeOnlineTenancy();

        $html = $this->actingAs($t['tenant'])->get(route('tenancy.show', $t['reservation']))->assertOk()->getContent();

        $this->assertStringContainsString('in cash', $html);
        $this->assertStringContainsString('Hand over the exact amount and ask for a receipt.', $html);
        $this->assertStringContainsString('nothing counts as paid until', $html);
    }
}
