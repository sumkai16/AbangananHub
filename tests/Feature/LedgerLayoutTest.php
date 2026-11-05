<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\Reservation;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\Support\CreatesPaymentFixtures;
use Tests\TestCase;

/**
 * The landlord's rent ledger page: no Months table, no tabs, a payment report first, the lease bars grouped by year
 * with "See more", and the records (payments, deposit, voided) as plain sections on the page.
 */
class LedgerLayoutTest extends TestCase
{
    use CreatesMarketplaceFixtures, CreatesPaymentFixtures, DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        config(['rentals.online_payments_enabled' => false]);
        Carbon::setTestNow('2026-10-14 10:00:00');
    }

    private function page(array $t): string
    {
        return $this->actingAs($t['landlord'])->get(route('landlord.payments.ledger', $t['reservation']))->assertOk()->getContent();
    }

    public function test_there_is_no_months_table_no_needs_action_card_and_no_tabs(): void
    {
        $t = $this->makeTenancy();

        $html = $this->page($t);

        $this->assertStringNotContainsString('ledger-panel-months', $html);
        $this->assertStringNotContainsString('Needs action', $html);
        $this->assertStringNotContainsString('role="tablist"', $html);
        $this->assertStringNotContainsString('role="tabpanel"', $html);
        $this->assertStringContainsString('Payments', $html);
    }

    public function test_a_payment_report_comes_before_the_balance(): void
    {
        $t = $this->makeTenancy();
        $t['reservation']->paymentClaims()->create([
            'claim_type' => 'Monthly', 'billing_period' => '2026-08-01', 'amount' => 5000, 'payment_method' => 'Cash',
            'paid_on' => '2026-10-13', 'status' => 'Pending', 'filed_by' => $t['tenant']->user_id,
        ]);

        $html = $this->page($t);

        $claim = strpos($html, 'Payment to confirm');
        $balance = strpos($html, 'Balance due');
        $this->assertNotFalse($claim);
        $this->assertNotFalse($balance);
        $this->assertLessThan($balance, $claim, 'The report to answer is the first thing under the name.');
    }

    public function test_the_deposit_and_voided_sections_still_render_on_the_page(): void
    {
        $t = $this->makeTenancy();
        $this->makePayment($t['reservation'], ['payment_type' => 'Deposit', 'billing_period' => null, 'amount' => 5000]);
        $wrong = $this->makeRecordedPayment($t['reservation'], $t['landlord'], ['billing_period' => '2026-08-01', 'amount' => 4000]);
        $wrong->update(['status' => 'Voided', 'voided_at' => now(), 'voided_by' => $t['landlord']->user_id, 'void_reason' => 'wrong_amount']);

        $html = $this->page($t);

        $this->assertStringContainsString('Security deposit', $html);
        $this->assertStringContainsString('id="deposit"', $html);
        $this->assertStringContainsString('Voided payments', $html);
        $this->assertStringContainsString('<details', $html);
    }

    public function test_an_older_year_folds_behind_see_more_but_an_unpaid_month_is_never_hidden(): void
    {
        // Moved in January 2024, so the lease spans 2024, 2025 and this year (2026).
        $t = $this->makeTenancy(['reservation_date' => '2024-01-01', 'target_move_in_date' => '2024-01-01']);
        foreach (range(0, 33) as $i) {
            $month = Carbon::parse('2024-01-01')->addMonths($i);
            // March 2024 stays unpaid, and so does everything from September 2026 on (owed this year).
            if ($month->format('Y-m') === '2024-03' || $month->greaterThanOrEqualTo(Carbon::parse('2026-09-01'))) {
                continue;
            }
            $this->makePayment($t['reservation'], ['billing_period' => $month->toDateString()]);
        }

        $html = $this->page($t);

        // 2025 is fully paid and not the current year: folded. 2024 holds an unpaid month and 2026 is this year: shown.
        $this->assertMatchesRegularExpression('/<div\s+x-show="moreYears"\s+x-cloak\s*>\s*<h3[^>]*>2025<\/h3>/', $html);
        $this->assertDoesNotMatchRegularExpression('/<div\s+x-show="moreYears"\s+x-cloak\s*>\s*<h3[^>]*>2024<\/h3>/', $html);
        $this->assertDoesNotMatchRegularExpression('/<div\s+x-show="moreYears"\s+x-cloak\s*>\s*<h3[^>]*>2026<\/h3>/', $html);
        $this->assertStringContainsString('See more · 12 more months', $html);
    }

    public function test_a_lease_with_nothing_folded_has_no_see_more(): void
    {
        // A five-month lease (August to December), all inside the current year.
        $t = $this->makeTenancy(['target_move_out_date' => '2026-12-31']);

        $this->assertStringNotContainsString('See more', $this->page($t));
    }

    public function test_an_unpaid_month_has_its_own_record_button_in_the_month_popup(): void
    {
        $t = $this->makeTenancy();
        $this->makePayment($t['reservation'], ['billing_period' => '2026-08-01']);

        $html = $this->page($t);

        // September and October are unpaid, so their popup payloads carry a `record` entry; the paid August one does not.
        $bs = chr(92);
        $this->assertSame(2, substr_count($html, $bs.'u0022record'.$bs.'u0022:'));
    }
}
