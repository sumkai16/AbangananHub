<?php

namespace Tests\Feature\Payments;

use App\Models\Payment;
use App\Models\PaymentClaim;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\Support\CreatesPaymentFixtures;
use Tests\TestCase;

/**
 * The tenancy page's pay area: the months owed as a list (oldest first), the way to pay beside it (a bottom sheet on a
 * phone), and the deposit one tap away. The money code is untouched; these pin what the page offers and that the
 * amount a month selection sends is still spread over the months oldest first.
 */
class RentPayPickerTest extends TestCase
{
    use CreatesMarketplaceFixtures, CreatesPaymentFixtures, DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        config(['rentals.online_payments_enabled' => false]);
        Carbon::setTestNow('2026-10-14 10:00:00');
    }

    /** Rent 5,000 due on the 5th since August, nothing paid: August, September and October are owed (15,000). */
    private function behind(): array
    {
        return $this->makeTenancy();
    }

    public function test_every_month_owed_is_a_row_with_its_amount_and_the_total_follows_the_selection(): void
    {
        $t = $this->behind();

        $html = $this->actingAs($t['tenant'])->get(route('tenancy.show', $t['reservation']))->assertOk()->getContent();

        foreach (['Aug 2026', 'Sep 2026', 'Oct 2026'] as $month) {
            $this->assertStringContainsString('aria-label="'.$month.'"', $html);
        }
        $this->assertStringContainsString('1 · What are you paying?', $html);
        $this->assertStringContainsString('2 · How do you want to pay?', $html);
        $this->assertStringContainsString('You owe 3 months', $html);
        // The running totals the tickboxes pick from: one month, two, all three.
        $this->assertStringContainsString('5000.00\u0022,\u002210000.00\u0022,\u002215000.00', $html);
    }

    public function test_one_month_owed_shows_one_fixed_row_and_no_pick_all(): void
    {
        $t = $this->behind();
        $this->makePayment($t['reservation'], ['billing_period' => '2026-08-01']);
        $this->makePayment($t['reservation'], ['billing_period' => '2026-09-01']);

        $html = $this->actingAs($t['tenant'])->get(route('tenancy.show', $t['reservation']))->assertOk()->getContent();

        $this->assertStringContainsString('aria-label="Oct 2026"', $html);
        $this->assertStringNotContainsString('aria-label="Aug 2026"', $html);
        $this->assertStringNotContainsString('You owe', $html);
    }

    public function test_the_phone_gets_a_sticky_total_bar_and_a_bottom_sheet_and_a_report_hides_both(): void
    {
        $t = $this->behind();
        $page = fn () => $this->actingAs($t['tenant'])->get(route('tenancy.show', $t['reservation']))->assertOk();

        $page()->assertSee('max-lg:fixed', false)->assertSee('open-pay-sheet', false)->assertSee('Pay rent', false);

        $this->actingAs($t['tenant'])->post(route('paymentClaims.store', $t['reservation']), [
            'accept_terms' => '1', 'claim_type' => 'Monthly', 'billing_period' => '2026-08-01', 'amount' => 15000,
            'payment_method' => 'GCash', 'reference_no' => 'PICK-1', 'paid_on' => '2026-10-13',
        ])->assertSessionHasNoErrors();

        // A waiting report is the one thing on screen: no months to pick, no sheet, no second way to pay.
        $page()->assertSee('Your payment report is waiting for', false)->assertDontSee('1 · What are you paying?', false)->assertDontSee('max-lg:fixed', false);
    }

    public function test_picking_two_of_three_months_reports_that_amount_and_it_lands_oldest_first(): void
    {
        $t = $this->behind();

        $this->actingAs($t['tenant'])->post(route('paymentClaims.store', $t['reservation']), [
            'accept_terms' => '1', 'claim_type' => 'Monthly', 'billing_period' => '2026-08-01', 'amount' => 10000,
            'payment_method' => 'GCash', 'reference_no' => 'PICK-2', 'paid_on' => '2026-10-13',
        ])->assertSessionHasNoErrors();

        $claim = PaymentClaim::firstOrFail();
        $this->actingAs($t['landlord'])->post(route('landlord.paymentClaims.confirm', $claim))->assertSessionHasNoErrors();

        $paid = Payment::where('payment_claim_id', $claim->payment_claim_id)->orderBy('billing_period')->pluck('billing_period')
            ->map(fn ($d) => $d->format('Y-m'))->all();
        $this->assertSame(['2026-08', '2026-09'], $paid);
    }

    public function test_a_deposit_shows_as_a_plain_tile_and_a_tab_without_a_details_link(): void
    {
        $t = $this->behind();
        $this->makePayment($t['reservation'], ['payment_type' => 'Deposit', 'amount' => 5000, 'billing_period' => null]);

        $html = $this->actingAs($t['tenant'])->get(route('tenancy.show', $t['reservation']))->assertOk()->getContent();

        $this->assertStringContainsString('Deposit held', $html);
        $this->assertStringNotContainsString('Deposit details', $html);
        $this->assertStringNotContainsString("\$dispatch('open-deposit')", $html);
        $this->assertStringContainsString('role="tablist"', $html);
        $this->assertStringContainsString('id="deposit"', $html);
    }

    public function test_without_a_deposit_there_are_no_tabs(): void
    {
        $t = $this->behind();

        $html = $this->actingAs($t['tenant'])->get(route('tenancy.show', $t['reservation']))->assertOk()->getContent();

        $this->assertStringNotContainsString('role="tablist"', $html);
        $this->assertStringContainsString('No deposit recorded', $html);
    }
}
