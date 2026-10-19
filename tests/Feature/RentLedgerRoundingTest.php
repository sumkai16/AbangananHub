<?php

namespace Tests\Feature;

use App\Services\RentLedger;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\Support\CreatesPaymentFixtures;
use Tests\TestCase;

/**
 * Money is summed as floats, so three payments of 1500.10 come to 4500.299999999999 and used to fall one
 * fraction of a centavo short of a 4500.30 rent: a fully paid month read as partly paid.
 */
class RentLedgerRoundingTest extends TestCase
{
    use CreatesMarketplaceFixtures, CreatesPaymentFixtures, DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-08-20 10:00:00');
    }

    public function test_payments_that_add_up_to_the_rent_count_as_paid(): void
    {
        $t = $this->makeTenancy(['agreed_monthly_rent' => 4500.30]);
        foreach ([1, 2, 3] as $_) {
            $this->makePayment($t['reservation'], ['amount' => 1500.10, 'billing_period' => '2026-08-01']);
        }

        $ledger = RentLedger::for($t['reservation']->fresh());
        $period = $ledger->periods()->first();

        $this->assertSame('paid', $period['status']);
        $this->assertSame(0.0, $period['balance']);
        $this->assertSame(0.0, $ledger->summary()['outstanding']);
        $this->assertNull($ledger->unsettledPeriods()->first(), 'nothing left to pay online');
    }

    public function test_a_genuinely_short_month_is_still_not_paid(): void
    {
        $t = $this->makeTenancy(['agreed_monthly_rent' => 4500.30]);
        foreach ([1, 2] as $_) {
            $this->makePayment($t['reservation'], ['amount' => 1500.10, 'billing_period' => '2026-08-01']);
        }

        $period = RentLedger::for($t['reservation']->fresh())->periods()->first();

        $this->assertNotSame('paid', $period['status']);
        $this->assertSame(1500.10, $period['balance']);
    }
}
