<?php

namespace Tests\Feature\Payments;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\Support\CreatesPaymentFixtures;
use Tests\TestCase;

/**
 * Payouts is a tab on the landlord's Payments page (Rent | Payouts), not its own sidebar item, and the GCash
 * details are set there with their own Save button instead of at the bottom of Account Settings.
 */
class PayoutsTabTest extends TestCase
{
    use CreatesMarketplaceFixtures, CreatesPaymentFixtures, DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        config(['rentals.online_payments_enabled' => true, 'broadcasting.default' => 'null']);
    }

    public function test_payments_shows_rent_and_payouts_tabs_and_the_sidebar_has_no_payouts_item(): void
    {
        $t = $this->makeTenancy();
        $this->makePayment($t['reservation'], [
            'amount' => 4400, 'billing_period' => null, 'payment_type' => 'Initial', 'payment_method' => 'GCash',
            'payout_status' => 'Pending Payout',
        ]);

        $html = $this->actingAs($t['landlord'])->get(route('landlord.payments.index'))->assertOk()->getContent();

        $this->assertStringContainsString('aria-label="Payments sections"', $html);
        $this->assertStringContainsString(route('landlord.payouts.index'), $html);
        $this->assertStringContainsString('₱4,400.00', $html, 'the Payouts tab carries what is waiting to be sent');
        $this->assertDoesNotMatchRegularExpression('/<span[^>]*>Payouts<\/span>/', $html, 'no Payouts item in the sidebar');
    }

    public function test_no_tabs_while_online_payments_are_off_and_nothing_is_owed(): void
    {
        config(['rentals.online_payments_enabled' => false]);
        $landlord = $this->makeLandlord();

        $this->actingAs($landlord)->get(route('landlord.payments.index'))
            ->assertOk()->assertDontSee('aria-label="Payments sections"', false);
    }

    public function test_the_payouts_tab_lights_payments_in_the_sidebar_and_opens_the_gcash_form_when_none_is_saved(): void
    {
        $landlord = $this->makeLandlord();

        $this->actingAs($landlord)->get(route('landlord.payouts.index'))
            ->assertOk()
            ->assertSee('Where your payouts go')
            ->assertSee('name="gcash_number"', false)
            ->assertSee('name="return_to" value="payouts"', false)
            ->assertSee('Save payout details');
    }

    public function test_saving_gcash_from_the_payouts_tab_returns_there_and_leaves_the_business_alone(): void
    {
        $landlord = $this->makeLandlord();
        $landlord->rentalBusiness()->create(['business_name' => 'Santos Rentals']);

        $this->actingAs($landlord)->patch(route('landlord.profile.update'), [
            'return_to' => 'payouts', 'gcash_number' => '0917 111 2222', 'gcash_account_name' => 'Test Landlord', 'accepts_online' => '1',
        ])->assertRedirect(route('landlord.payouts.index'));

        $landlord = $landlord->fresh();
        $this->assertSame('09171112222', $landlord->payoutAccount->gcash_number);
        $this->assertSame('Santos Rentals', $landlord->rentalBusiness->business_name);

        $this->get(route('landlord.payouts.index'))->assertOk()->assertSee('GCash 0917 111 2222')->assertSee('Change');
    }

    public function test_changing_a_saved_gcash_still_needs_the_password(): void
    {
        $landlord = $this->makeLandlord();
        $landlord->savePayoutAccount('09171234567', 'Test Landlord');

        $this->actingAs($landlord)->from(route('landlord.payouts.index'))->patch(route('landlord.profile.update'), [
            'return_to' => 'payouts', 'gcash_number' => '09179998888', 'gcash_account_name' => 'Test Landlord',
        ])->assertRedirect(route('landlord.payouts.index'))->assertSessionHasErrors('current_password');

        $this->assertSame('09171234567', $landlord->fresh()->payoutAccount->gcash_number);
    }

    public function test_the_gcash_form_is_on_payouts_and_settings_and_not_on_my_profile(): void
    {
        $landlord = $this->makeLandlord();
        $this->actingAs($landlord);

        // One shared form (landlord/payouts/partials/_account): each copy brings the landlord back to its own page.
        $this->get(route('landlord.payouts.index'))->assertOk()
            ->assertSee('name="gcash_number"', false)->assertSee('name="return_to" value="payouts"', false);
        $this->get(route('profile.edit'))->assertOk()
            ->assertSee('name="gcash_number"', false)->assertSee('name="return_to" value="settings"', false);
        $this->get(route('landlord.profile.me'))->assertOk()
            ->assertDontSee('name="gcash_number"', false)->assertDontSee('Payout (GCash)');
    }
}
