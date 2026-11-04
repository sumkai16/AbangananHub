<?php

namespace Tests\Feature\Payments;

use App\Models\PaymentClaim;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\Support\CreatesPaymentFixtures;
use Tests\TestCase;

/**
 * Settings > Payments: the landlord picks which ways tenants may pay them (online, their own QR, cash), and the
 * move-in and rent payment choices, the claim endpoint and the API all follow it (App\Support\PaymentMethods).
 */
class PaymentMethodsTest extends TestCase
{
    use CreatesMarketplaceFixtures, CreatesPaymentFixtures, DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        config(['rentals.online_payments_enabled' => true]);
        Carbon::setTestNow('2026-10-14 10:00:00');
    }

    private function choose($landlord, bool $online, bool $qr, bool $cash)
    {
        return $this->actingAs($landlord)->patch(route('landlord.paymentMethods.update'), [
            'accepts_online' => $online ? '1' : '0', 'accepts_qr' => $qr ? '1' : '0', 'accepts_cash' => $cash ? '1' : '0',
        ]);
    }

    // ─── Settings ──────────────────────────────────────────────

    public function test_settings_has_the_methods_and_the_gcash_form(): void
    {
        $html = $this->actingAs($this->makeLandlord())->get(route('profile.edit'))->assertOk()->getContent();

        $this->assertStringContainsString('How tenants can pay you', $html);
        $this->assertStringContainsString('name="accepts_qr"', $html);
        $this->assertStringContainsString('name="accepts_cash"', $html);
        // No GCash yet: online is there but cannot be ticked, and the GCash form is right below it.
        $this->assertStringContainsString('Save your GCash below to turn this on.', $html);
        $this->assertStringContainsString('name="gcash_number"', $html);
        $this->assertStringContainsString('name="return_to" value="settings"', $html);
    }

    public function test_a_tenant_gets_no_payment_section(): void
    {
        $this->actingAs($this->makeTenant())->get(route('profile.edit'))->assertOk()
            ->assertDontSee('How tenants can pay you')->assertDontSee('name="gcash_number"', false);
    }

    public function test_gcash_saved_from_settings_comes_back_to_settings(): void
    {
        $landlord = $this->makeLandlord();

        $this->actingAs($landlord)->patch(route('landlord.profile.update'), [
            'return_to' => 'settings', 'gcash_number' => '09171112222', 'gcash_account_name' => $landlord->first_name.' '.$landlord->last_name,
        ])->assertRedirect(route('profile.edit').'#payments');

        $this->assertSame('09171112222', $landlord->fresh()->payoutAccount->gcash_number);
    }

    public function test_turning_everything_off_is_refused(): void
    {
        $landlord = $this->makeLandlord();

        // No GCash, so ticking online alone is not a way to be paid either.
        $this->choose($landlord, true, false, false)
            ->assertRedirect(route('profile.edit').'#payments')->assertSessionHasErrors('payment_methods');

        $this->assertTrue($landlord->fresh()->accepts_qr_payments);
        $this->assertTrue($landlord->fresh()->accepts_cash_payments);
    }

    public function test_online_only_is_allowed_with_gcash(): void
    {
        $t = $this->makeOnlineTenancy();

        $this->choose($t['landlord'], true, false, false)->assertSessionHasNoErrors()->assertSessionHas('success', 'Payment methods saved.');

        $landlord = $t['landlord']->fresh();
        $this->assertFalse($landlord->accepts_qr_payments);
        $this->assertFalse($landlord->accepts_cash_payments);
        $this->assertTrue($landlord->acceptsOnlinePayments());
    }

    // ─── What the tenant sees ──────────────────────────────────

    public function test_no_qr_leaves_online_and_cash(): void
    {
        $t = $this->makeOnlineTenancy();
        $this->choose($t['landlord'], true, false, true);
        $name = $t['landlord']->first_name;

        $html = $this->actingAs($t['tenant'])->get(route('tenancy.show', $t['reservation']))->assertOk()->getContent();

        $this->assertStringContainsString('Pay online', $html);
        $this->assertStringContainsString("Pay {$name} directly", $html);
        $this->assertStringContainsString('Cash, in person', $html);
        $this->assertStringContainsString('Hand over the exact amount', $html);
        $this->assertStringNotContainsString("{$name}&rsquo;s QR", $html);
    }

    public function test_no_cash_hides_the_cash_way(): void
    {
        $t = $this->makeTenancy();
        $this->choose($t['landlord'], false, true, false);

        $this->actingAs($t['tenant'])->get(route('tenancy.show', $t['reservation']))->assertOk()
            ->assertSee('by QR')->assertDontSee('Pay cash')->assertDontSee('Hand over the exact amount');
    }

    public function test_online_only_shows_one_card(): void
    {
        $t = $this->makeOnlineTenancy();
        $this->choose($t['landlord'], true, false, false);
        $name = $t['landlord']->first_name;

        $this->actingAs($t['tenant'])->get(route('tenancy.show', $t['reservation']))->assertOk()
            ->assertSee('Pay online')->assertDontSee("Pay {$name} directly")->assertDontSee('Pay cash');
    }

    public function test_the_tenant_is_never_left_with_no_way_to_pay(): void
    {
        $t = $this->makeOnlineTenancy();
        $this->choose($t['landlord'], true, false, false);
        // Online drops out afterwards (GCash removed): both direct ways come back.
        $t['landlord']->savePayoutAccount(null, null);

        $this->actingAs($t['tenant'])->get(route('tenancy.show', $t['reservation']))->assertOk()
            ->assertDontSee('Pay online')->assertSee('by QR')->assertSee('Pay cash');
    }

    // ─── Server side ───────────────────────────────────────────

    public function test_a_report_for_a_turned_off_way_is_refused(): void
    {
        $t = $this->makeTenancy();
        $this->choose($t['landlord'], false, true, false);

        $this->actingAs($t['tenant'])->post(route('paymentClaims.store', $t['reservation']), [
            'accept_terms' => '1', 'claim_type' => 'Monthly', 'billing_period' => '2026-08-01',
            'amount' => 5000, 'payment_method' => 'Cash', 'paid_on' => '2026-10-13',
        ])->assertSessionHasErrors('payment');
        $this->assertSame(0, PaymentClaim::count());

        // QR is still on, so a QR report goes through.
        $this->post(route('paymentClaims.store', $t['reservation']), [
            'accept_terms' => '1', 'claim_type' => 'Monthly', 'billing_period' => '2026-08-01',
            'amount' => 5000, 'payment_method' => 'GCash', 'reference_no' => 'GC777', 'paid_on' => '2026-10-13',
        ])->assertSessionHasNoErrors();
        $this->assertSame(1, PaymentClaim::count());
    }

    public function test_the_api_sends_the_methods_and_no_qr_when_qr_is_off(): void
    {
        $t = $this->makeTenancy();
        $this->choose($t['landlord'], false, false, true);

        Sanctum::actingAs($t['tenant']);
        $this->getJson('/api/v1/tenant/tenancy/'.$t['reservation']->reservation_id)->assertOk()
            ->assertJsonPath('data.payment_methods', ['online' => false, 'qr' => false, 'cash' => true])
            ->assertJsonPath('data.payment_qr', null);
    }
}
