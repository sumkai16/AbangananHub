<?php

namespace Tests\Feature\Payments;

use App\Models\Payment;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\Support\CreatesPaymentFixtures;
use Tests\TestCase;

/** A tenant can open a printable receipt for money the landlord confirmed or recorded, and for nothing else. */
class TenantReceiptTest extends TestCase
{
    use CreatesMarketplaceFixtures, CreatesPaymentFixtures, DatabaseTransactions;

    private function url($t, Payment $payment): string
    {
        return route('tenancy.receipt', [$t['reservation'], $payment]);
    }

    public function test_the_tenant_sees_a_receipt_for_a_landlord_recorded_payment(): void
    {
        $t = $this->makeTenancy();
        $payment = $this->makeRecordedPayment($t['reservation'], $t['landlord'], ['reference_no' => 'GC-123456']);

        $this->actingAs($t['tenant'])->get($this->url($t, $payment))
            ->assertOk()
            ->assertSee('Payment receipt')
            ->assertSee('GC-123456')
            ->assertSee('5,000.00')
            ->assertSee('Five thousand pesos only');
    }

    public function test_the_tenancy_page_links_to_the_receipt(): void
    {
        $t = $this->makeTenancy();
        $payment = $this->makeRecordedPayment($t['reservation'], $t['landlord'], ['payment_type' => 'Deposit', 'billing_period' => null]);

        $this->actingAs($t['tenant'])->get(route('tenancy.show', $t['reservation']))
            ->assertOk()
            ->assertSee($this->url($t, $payment), false);
    }

    public function test_a_payment_that_went_through_paymongo_has_no_receipt_here(): void
    {
        $t = $this->makeTenancy();
        $payment = $this->makePayment($t['reservation']);

        $this->actingAs($t['tenant'])->get($this->url($t, $payment))->assertNotFound();
    }

    public function test_a_voided_payment_has_no_receipt(): void
    {
        $t = $this->makeTenancy();
        $payment = $this->makeRecordedPayment($t['reservation'], $t['landlord'], ['status' => 'Voided']);

        $this->actingAs($t['tenant'])->get($this->url($t, $payment))->assertNotFound();
    }

    public function test_another_tenant_cannot_open_it(): void
    {
        $t = $this->makeTenancy();
        $payment = $this->makeRecordedPayment($t['reservation'], $t['landlord']);

        $this->actingAs($this->makeTenant())->get($this->url($t, $payment))->assertForbidden();
    }

    public function test_a_payment_from_a_different_tenancy_is_not_reachable_through_this_one(): void
    {
        $t = $this->makeTenancy();
        $other = $this->makeTenancy(tenant: $t['tenant']);
        $payment = $this->makeRecordedPayment($other['reservation'], $other['landlord']);

        $this->actingAs($t['tenant'])->get($this->url($t, $payment))->assertNotFound();
    }

    public function test_the_amount_is_written_out_with_centavos(): void
    {
        $t = $this->makeTenancy();

        $this->assertSame('Four thousand pesos and 50/100 only', $this->makePayment($t['reservation'], ['amount' => 4000.50])->amountInWords());
        $this->assertSame('One peso only', $this->makePayment($t['reservation'], ['amount' => 1])->amountInWords());
    }

    public function test_the_landlord_receipt_uses_the_same_document(): void
    {
        $t = $this->makeTenancy();
        $payment = $this->makeRecordedPayment($t['reservation'], $t['landlord']);

        $this->actingAs($t['landlord'])->get(route('landlord.payments.receipt', $payment))
            ->assertOk()
            ->assertSee('Payment receipt')
            ->assertSee('Five thousand pesos only');
    }
}
