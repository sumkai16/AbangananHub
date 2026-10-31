<?php

namespace Tests\Feature\Payments;

use App\Models\Conversation;
use App\Models\PaymentClaim;
use App\Models\PaymentQrShare;
use App\Models\Reservation;
use App\Support\LeaseTerms;
use App\Support\QrPh;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\Support\CreatesPaymentFixtures;
use Tests\TestCase;

/**
 * The screens of the direct-payment flow render for the right person with the right call to action.
 * Model::preventLazyLoading is on in tests, so a missing eager load fails here rather than in a browser.
 */
class DirectPaymentPagesTest extends TestCase
{
    use CreatesMarketplaceFixtures, CreatesPaymentFixtures, DatabaseTransactions;

    private const AJAX = ['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'text/html'];

    protected function setUp(): void
    {
        parent::setUp();

        config(['rentals.online_payments_enabled' => false, 'broadcasting.default' => 'null']);
        Carbon::setTestNow('2026-10-14 10:00:00');
    }

    private function signedWithChat(): array
    {
        $landlord = $this->makeLandlord();
        $tenant = $this->makeTenant();
        $unit = $this->makeUnit($this->makeProperty($landlord));
        $conversation = Conversation::create([
            'tenant_id' => $tenant->user_id, 'landlord_id' => $landlord->user_id,
            'property_id' => $unit->property_id, 'unit_id' => $unit->unit_id, 'type' => 'inquiry',
        ]);
        $reservation = Reservation::create([
            'tenant_id' => $tenant->user_id, 'property_id' => $unit->property_id, 'unit_id' => $unit->unit_id,
            'conversation_id' => $conversation->conversation_id, 'rental_status' => 'Rental Agreement Signed',
            'reservation_date' => '2026-10-01', 'target_move_in_date' => '2026-10-15', 'agreed_monthly_rent' => 5000,
            'agreed_at' => now(),
        ]);
        $reservation->update(['lease_snapshot' => LeaseTerms::snapshot($reservation)]);

        return compact('landlord', 'tenant', 'conversation', 'reservation');
    }

    private function shareQr($landlord, $conversation, string $name = 'AXCEE CABUSAS'): void
    {
        $this->actingAs($landlord)->postJson(route('paymentQr.store', $conversation), ['payload' => QrPh::make($name), 'note' => 'Pay by the 5th'])->assertCreated();
    }

    public function test_the_deposit_follows_the_signed_agreement_when_the_unit_is_edited_later(): void
    {
        ['reservation' => $reservation] = $this->signedWithChat();
        $frozen = $reservation->agreedDeposit();
        $this->assertGreaterThan(0, $frozen);

        $reservation->unit->update(['security_deposit' => 0]);

        $this->assertSame($frozen, $reservation->fresh()->agreedDeposit());
    }

    public function test_the_agreement_page_asks_the_tenant_to_pay_the_landlord_and_shows_the_shared_qr(): void
    {
        ['landlord' => $landlord, 'tenant' => $tenant, 'conversation' => $conversation, 'reservation' => $reservation] = $this->signedWithChat();

        $this->actingAs($tenant)->get(route('agreements.show', $reservation))
            ->assertOk()
            ->assertSee('Pay your landlord to move in')
            ->assertSee('hasn&rsquo;t shared a payment QR yet', false)
            ->assertDontSee('Pay Now');

        $this->shareQr($landlord, $conversation);

        $this->actingAs($tenant)->get(route('agreements.show', $reservation))
            ->assertOk()
            ->assertSee('AXCEE CABUSAS')
            ->assertSee('<svg', false)
            ->assertSee('Pay by the 5th');
    }

    public function test_with_online_payments_on_the_tenant_chooses_between_online_and_direct(): void
    {
        config(['rentals.online_payments_enabled' => true]);
        ['landlord' => $landlord, 'tenant' => $tenant, 'reservation' => $reservation] = $this->signedWithChat();
        $landlord->savePayoutAccount('09171234567', 'Test Landlord');

        $this->actingAs($tenant)->get(route('agreements.show', $reservation))
            ->assertOk()
            ->assertSee('How do you want to pay to move in?')
            ->assertSee('Pay online')
            ->assertSee('Pay '.$landlord->first_name.' by QR')
            ->assertSee('Pay cash')
            // The direct side is on the page too, so choosing it needs no reload.
            ->assertSee('Pay your landlord to move in');
    }

    public function test_a_landlord_without_gcash_details_can_only_be_paid_directly(): void
    {
        config(['rentals.online_payments_enabled' => true]);
        ['tenant' => $tenant, 'reservation' => $reservation] = $this->signedWithChat();

        $this->actingAs($tenant)->get(route('agreements.show', $reservation))
            ->assertOk()->assertDontSee('Pay online')->assertSee('Pay cash')->assertSee('Pay your landlord to move in');
    }

    public function test_a_landlord_who_opted_out_of_online_payments_is_paid_directly_only(): void
    {
        config(['rentals.online_payments_enabled' => true]);
        ['landlord' => $landlord, 'tenant' => $tenant, 'reservation' => $reservation] = $this->signedWithChat();
        $landlord->savePayoutAccount('09171234567', 'Test Landlord');
        $landlord->setAcceptsOnlinePayments(false);

        $this->assertFalse($landlord->acceptsOnlinePayments());
        $this->actingAs($tenant)->get(route('agreements.show', $reservation))
            ->assertOk()->assertDontSee('Pay online')->assertSee('Pay cash')->assertSee('Pay your landlord to move in');
    }

    public function test_the_tenant_sees_a_pending_report_and_a_rejection_reason(): void
    {
        ['landlord' => $landlord, 'tenant' => $tenant, 'reservation' => $reservation] = $this->signedWithChat();

        $this->actingAs($tenant)->post(route('paymentClaims.store', $reservation), [
            'claim_type' => 'MoveIn', 'amount' => 10000, 'payment_method' => 'Maya', 'reference_no' => 'MY12345', 'paid_on' => '2026-10-13',
        ]);

        $this->actingAs($tenant)->get(route('agreements.show', $reservation->fresh()))
            ->assertSee('Waiting for')->assertSee('MY12345');

        $this->actingAs($landlord)->post(route('landlord.paymentClaims.reject', PaymentClaim::firstOrFail()), ['rejection_reason' => 'Not in my account']);

        $this->actingAs($tenant)->get(route('agreements.show', $reservation->fresh()))
            ->assertSee('couldn&rsquo;t confirm your last report', false)->assertSee('Not in my account');
    }

    public function test_after_confirmation_the_tenant_can_confirm_move_in_from_the_agreement_page(): void
    {
        ['landlord' => $landlord, 'tenant' => $tenant, 'reservation' => $reservation] = $this->signedWithChat();
        $reservation->update(['move_in_paid_at' => now()]);

        $this->actingAs($tenant)->get(route('agreements.show', $reservation))
            ->assertOk()->assertSee('Payment confirmed by')->assertSee("I've moved in", false);
    }

    public function test_the_landlords_chat_shows_the_report_to_confirm_and_the_record_form(): void
    {
        ['landlord' => $landlord, 'tenant' => $tenant, 'conversation' => $conversation, 'reservation' => $reservation] = $this->signedWithChat();

        $this->actingAs($landlord)->get(route('conversations.show', $conversation), self::AJAX)
            ->assertOk()->assertSee('waiting for')->assertSee('Record move-in payment')->assertSee('Share payment QR');

        $this->actingAs($tenant)->post(route('paymentClaims.store', $reservation), [
            'claim_type' => 'MoveIn', 'amount' => 10000, 'payment_method' => 'GCash', 'reference_no' => 'GC999', 'paid_on' => '2026-10-13',
        ]);

        $this->actingAs($landlord)->get(route('conversations.show', $conversation), self::AJAX)
            ->assertOk()->assertSee('Payment to confirm')->assertSee('GC999')->assertSee('Confirm payment');
    }

    public function test_both_sides_see_the_qr_card_in_the_chat_and_a_replaced_one_is_marked(): void
    {
        ['landlord' => $landlord, 'tenant' => $tenant, 'conversation' => $conversation] = $this->signedWithChat();
        $this->shareQr($landlord, $conversation, 'OLD ACCOUNT');
        $this->shareQr($landlord, $conversation, 'NEW ACCOUNT');

        foreach ([$tenant, $landlord] as $viewer) {
            $this->actingAs($viewer)->get(route('conversations.show', $conversation), self::AJAX)
                ->assertOk()->assertSee('NEW ACCOUNT')->assertSee('Replaced')->assertSee('Do not pay to it');
        }

        $this->assertSame(1, PaymentQrShare::whereNull('replaced_at')->count());
    }

    public function test_the_tenants_chat_has_no_share_button(): void
    {
        ['tenant' => $tenant, 'conversation' => $conversation] = $this->signedWithChat();

        $this->actingAs($tenant)->get(route('conversations.show', $conversation), self::AJAX)
            ->assertOk()->assertDontSee('Share payment QR');
    }

    public function test_the_tenancy_pages_show_the_direct_pay_card_and_the_review_card(): void
    {
        ['landlord' => $landlord, 'tenant' => $tenant, 'reservation' => $reservation] = $this->makeTenancy(['target_move_in_date' => '2026-10-01', 'reservation_date' => '2026-10-01']);

        $this->actingAs($tenant)->get(route('tenancy.show', $reservation))
            ->assertOk()->assertSee('Pay your landlord')->assertSee('I&rsquo;ve paid', false)->assertDontSee('now</button>', false);

        $this->actingAs($tenant)->post(route('paymentClaims.store', $reservation), [
            'claim_type' => 'Monthly', 'billing_period' => '2026-10-01', 'amount' => 5000,
            'payment_method' => 'GoTyme', 'reference_no' => 'GT55', 'paid_on' => '2026-10-10',
        ])->assertSessionHasNoErrors();

        $this->actingAs($tenant)->get(route('tenancy.show', $reservation))->assertOk()->assertSee('GT55');
        $this->actingAs($landlord)->get(route('landlord.tenancies.show', $reservation))
            ->assertOk()->assertSee('Payment to confirm')->assertSee('GT55');
    }

    public function test_the_landlords_reservation_list_flags_a_report_and_offers_move_in_confirmation(): void
    {
        ['landlord' => $landlord, 'tenant' => $tenant, 'reservation' => $reservation] = $this->signedWithChat();

        $this->actingAs($tenant)->post(route('paymentClaims.store', $reservation), [
            'claim_type' => 'MoveIn', 'amount' => 10000, 'payment_method' => 'GCash', 'reference_no' => 'GC1', 'paid_on' => '2026-10-13',
        ]);

        $this->actingAs($landlord)->get(route('landlord.reservations.index'))->assertOk()->assertSee('Payment to confirm');

        $reservation->update(['move_in_paid_at' => now()]);

        $this->actingAs($landlord)->get(route('landlord.reservations.index'))->assertOk()->assertSee('Confirm move-in');
    }

    public function test_payout_screens_are_hidden_while_online_payments_are_off_and_return_with_them(): void
    {
        ['landlord' => $landlord] = $this->signedWithChat();

        $this->actingAs($landlord)->get(route('landlord.dashboard'))
            ->assertOk()->assertDontSee('Payout from AbangananHub')->assertDontSee(route('landlord.payouts.index'));

        config(['rentals.online_payments_enabled' => true]);

        $this->actingAs($landlord)->get(route('landlord.dashboard'))
            ->assertOk()->assertSee(route('landlord.payouts.index'));
    }

    public function test_the_dashboard_counts_payments_waiting_for_confirmation(): void
    {
        ['landlord' => $landlord, 'tenant' => $tenant, 'reservation' => $reservation] = $this->signedWithChat();

        $this->actingAs($tenant)->post(route('paymentClaims.store', $reservation), [
            'claim_type' => 'MoveIn', 'amount' => 10000, 'payment_method' => 'GCash', 'reference_no' => 'GC1', 'paid_on' => '2026-10-13',
        ]);

        $this->actingAs($landlord)->get(route('landlord.dashboard'))
            ->assertOk()->assertSee('1 payment is waiting for you to confirm');
    }
}
