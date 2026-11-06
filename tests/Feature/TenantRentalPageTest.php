<?php

namespace Tests\Feature;

use App\Models\PaymentClaim;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\Support\CreatesPaymentFixtures;
use Tests\TestCase;

/**
 * The tenant's single rental page follows the landlord's rent ledger (plans/tenant-rental-page-redesign.md): a Balance
 * tile with the pay action, four fact tiles, the Lease strip, Payments, Security deposit.
 */
class TenantRentalPageTest extends TestCase
{
    use CreatesMarketplaceFixtures, CreatesPaymentFixtures, DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-14 10:00:00');
    }

    private function page(array $reservation = [])
    {
        $t = $this->makeTenancy($reservation);

        return [$t, $this->actingAs($t['tenant'])->get(route('tenancy.show', $t['reservation']))->assertOk()];
    }

    public function test_the_page_has_the_ledgers_layout_in_the_tenants_words(): void
    {
        [, $r] = $this->page();

        $r->assertSee('Balance due')
            ->assertSee('Monthly rent')->assertSee('Oldest unpaid')->assertSee('Paid to date')->assertSee('Deposit held')
            ->assertSee('Lease')->assertSee('Month 3 of 12')
            ->assertSee('Payments')->assertSee('My Rentals');
    }

    public function test_an_overdue_tenant_sees_what_is_owed_and_a_pay_button(): void
    {
        // Moved in Aug 1, 5,000 a month due on the 5th, nothing paid: three months behind.
        [, $r] = $this->page();

        $r->assertSee('₱15,000.00')
            ->assertSee('₱15,000.00 overdue')
            ->assertSee('across 3 months')
            ->assertSee('href="#pay"', false)
            ->assertSee('id="pay"', false);
    }

    public function test_a_tenant_with_nothing_owed_gets_no_pay_prompt_for_a_month_that_is_not_due(): void
    {
        [, $r] = $this->page(['target_move_in_date' => '2026-12-01']);

        $r->assertDontSee('overdue across')->assertDontSee('Oldest unpaid');
    }

    public function test_a_report_waiting_for_the_landlord_is_marked_in_payments(): void
    {
        $t = $this->makeTenancy();
        PaymentClaim::create([
            'reservation_id' => $t['reservation']->reservation_id, 'claim_type' => 'Monthly', 'billing_period' => '2026-08-01',
            'amount' => 5000, 'payment_method' => 'GCash', 'reference_no' => 'RENT-1', 'paid_on' => '2026-10-13',
            'status' => 'Pending', 'filed_by' => $t['tenant']->user_id,
        ]);

        $this->actingAs($t['tenant'])->get(route('tenancy.show', $t['reservation']))->assertOk()
            ->assertSee('to confirm')
            ->assertSee('Ref RENT-1')
            ->assertSee('See your payment report');
    }

    public function test_a_payment_the_tenant_made_is_listed_with_its_receipt(): void
    {
        $t = $this->makeTenancy();
        $payment = $this->makePayment($t['reservation'], ['amount' => 5000, 'payment_method' => 'GCash', 'reference_no' => 'GC-123', 'paid_at' => '2026-08-01', 'paymongo_payment_id' => 'pay_test_1']);

        $html = $this->actingAs($t['tenant'])->get(route('tenancy.show', $t['reservation']))->assertOk()
            ->assertSee('Aug 2026 Rent')->assertSee('GC-123')->getContent();

        $this->assertStringContainsString('Payments', $html);
        $this->assertSame($payment->hasReceipt(), str_contains($html, route('tenancy.receipt', [$t['reservation'], $payment])));
    }

    public function test_the_deposit_is_shown_read_only(): void
    {
        $t = $this->makeTenancy();
        $this->makePayment($t['reservation'], ['payment_type' => 'Deposit', 'amount' => 2500, 'billing_period' => null, 'paid_at' => '2026-07-20']);

        $this->actingAs($t['tenant'])->get(route('tenancy.show', $t['reservation']))->assertOk()
            ->assertSee('Security deposit')
            ->assertSee('Refundable at move-out')
            ->assertDontSee('Deduct from deposit');
    }

    public function test_an_ended_rental_says_so_and_offers_no_payment(): void
    {
        $t = $this->makeTenancy(['rental_status' => 'Completed', 'target_move_out_date' => '2026-09-30']);

        $this->actingAs($t['tenant'])->get(route('tenancy.show', $t['reservation']))->assertOk()
            ->assertSee('This rental ended on')->assertSee('Sep 30, 2026')
            ->assertDontSee('id="pay"', false)
            ->assertDontSee('Report a concern');
    }

    public function test_another_tenant_cannot_open_the_page(): void
    {
        $t = $this->makeTenancy();

        $this->actingAs($this->makeTenant())->get(route('tenancy.show', $t['reservation']))->assertForbidden();
    }

    public function test_the_landlords_ledger_still_draws_the_same_lease_strip(): void
    {
        $t = $this->makeTenancy();

        $this->actingAs($t['landlord'])->get(route('landlord.payments.ledger', $t['reservation']))->assertOk()
            ->assertSee('Month 3 of 12')->assertSee('aria-label="Rent by month, 2026"', false);
    }
}
