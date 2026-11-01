<?php

namespace Tests\Feature;

use App\Models\Reservation;
use App\Services\RentLedger;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\Support\CreatesPaymentFixtures;
use Tests\TestCase;

/**
 * An online tenant's move-in payment is the first month's rent, and the rent a tenant pays is the rent they
 * signed. Before this the ledger left `Initial` outside the monthly cycle, so after moving in the first month
 * read Due and "Pay now" was offered again, and checkout charged whatever the unit cost that day.
 */
class RentLedgerInitialTest extends TestCase
{
    use CreatesMarketplaceFixtures, CreatesPaymentFixtures, DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        // Default tenancy moves in 2026-08-01 at 5,000 a month, due on the 5th.
        Carbon::setTestNow('2026-08-20 10:00:00');
    }

    private function onlineInitial(Reservation $reservation, string $status = 'Held')
    {
        return $this->makePayment($reservation, [
            'payment_type' => 'Initial', 'billing_period' => null, 'status' => $status,
            'payment_method' => 'GCash', 'amount' => 5000,
        ]);
    }

    // ─── Initial settles the first month ────────────────────────

    public function test_an_online_initial_payment_settles_the_first_month(): void
    {
        $t = $this->makeTenancy();
        $this->onlineInitial($t['reservation']);

        $ledger = RentLedger::for($t['reservation']->fresh());

        $this->assertCount(1, $ledger->periods());
        $this->assertSame('paid', $ledger->periods()->first()['status']);
        $this->assertSame(0.0, $ledger->summary()['outstanding'], 'nothing is owed');
        $this->assertNull($ledger->unsettledPeriods()->first(), 'so no "Pay now" is offered for a month already paid');
    }

    public function test_the_initial_payment_is_counted_once_in_what_was_collected(): void
    {
        $t = $this->makeTenancy();
        $this->onlineInitial($t['reservation']);

        $ledger = RentLedger::for($t['reservation']->fresh());

        $this->assertSame(5000.0, $ledger->summary()['collected'], 'not 10,000');
        $this->assertCount(0, $ledger->otherCharges(), 'it is rent now, not a one-off charge');
        $this->assertCount(1, $ledger->monthlyTransactions(), 'and it shows in the rent payments list');
    }

    public function test_the_initial_payment_settles_only_the_first_month(): void
    {
        Carbon::setTestNow('2026-10-14 10:00:00');
        $t = $this->makeTenancy();
        $this->onlineInitial($t['reservation']);

        $ledger = RentLedger::for($t['reservation']->fresh());
        $periods = $ledger->periods();

        $this->assertSame(['Aug 2026', 'Sep 2026', 'Oct 2026'], $periods->pluck('label')->all());
        $this->assertSame('paid', $periods[0]['status']);
        $this->assertNotSame('paid', $periods[1]['status']);
        $this->assertSame('Sep 2026', $ledger->unsettledPeriods()->first()['label'], 'the next payable month is September');
        $this->assertSame(10000.0, $ledger->summary()['outstanding']);
    }

    public function test_a_checkout_that_was_never_paid_settles_nothing(): void
    {
        $t = $this->makeTenancy();
        $this->onlineInitial($t['reservation'], 'Pending');

        $ledger = RentLedger::for($t['reservation']->fresh());

        $this->assertNotSame('paid', $ledger->periods()->first()['status']);
        $this->assertSame(5000.0, $ledger->summary()['outstanding']);
    }

    public function test_a_walk_in_lump_the_landlord_typed_in_stays_outside_the_monthly_cycle(): void
    {
        $t = $this->makeTenancy();
        $this->makeRecordedPayment($t['reservation'], $t['landlord'], [
            'payment_type' => 'Initial', 'billing_period' => null, 'amount' => 5000, 'status' => 'Paid',
        ]);

        $ledger = RentLedger::for($t['reservation']->fresh());

        $this->assertNotSame('paid', $ledger->periods()->first()['status'], 'preserves the Aug 29 walk-in behaviour');
        $this->assertCount(1, $ledger->otherCharges());
    }

    // ─── when billing starts ────────────────────────────────────

    public function test_with_no_target_date_billing_starts_at_the_confirmed_move_in_not_the_inquiry(): void
    {
        Carbon::setTestNow('2026-10-14 10:00:00');
        $t = $this->makeTenancy([
            'target_move_in_date' => null,
            'reservation_date' => '2026-08-01',
            'tenant_confirmed_move_in_at' => '2026-09-10 09:00:00',
        ]);

        $periods = RentLedger::for($t['reservation']->fresh())->periods();

        $this->assertSame(['Sep 2026', 'Oct 2026'], $periods->pluck('label')->all(), 'no August: nobody lived there yet');
    }

    // ─── the rent is frozen when the lease is issued ────────────

    public function test_issuing_the_agreement_pins_the_rent_and_a_later_price_edit_does_not_move_it(): void
    {
        $t = $this->makeTenancy(['rental_status' => 'Under Negotiation', 'agreed_monthly_rent' => null]);
        $reservation = $t['reservation'];
        $reservation->unit->update(['rental_fee' => 6000]);

        $this->assertTrue($reservation->fresh()->advanceToPendingAgreement());

        $reservation = $reservation->fresh();
        $this->assertSame('6000.00', (string) $reservation->agreed_monthly_rent);
        $this->assertSame(6000.0, (float) $reservation->lease_snapshot['monthly_rent'], 'the lease and the charge agree');

        $reservation->unit->update(['rental_fee' => 9000]);

        $this->assertSame(6000.0, $reservation->fresh()->monthlyRent(), 'the landlord raising the price does not change what was signed');
    }

    public function test_an_agreed_rent_set_earlier_is_not_overwritten(): void
    {
        $t = $this->makeTenancy(['rental_status' => 'Under Negotiation', 'agreed_monthly_rent' => 4500]);
        $t['reservation']->unit->update(['rental_fee' => 6000]);

        $t['reservation']->fresh()->advanceToPendingAgreement();

        $this->assertSame('4500.00', (string) $t['reservation']->fresh()->agreed_monthly_rent);
    }

    public function test_the_backfill_pins_the_rent_of_leases_issued_before_this_change(): void
    {
        $t = $this->makeTenancy(['rental_status' => 'Rental Agreement Signed', 'agreed_monthly_rent' => null]);
        Reservation::whereKey($t['reservation']->getKey())->update(['lease_snapshot' => json_encode(['monthly_rent' => 7200])]);

        (require base_path('database/migrations/2026_10_15_000000_backfill_agreed_rent_from_lease_snapshot.php'))->up();

        $this->assertSame('7200.00', (string) $t['reservation']->fresh()->agreed_monthly_rent);
    }

    // ─── the one next step per status ───────────────────────────

    public function test_the_next_step_for_each_status(): void
    {
        $cases = [
            'Inquiry' => null,
            'Under Negotiation' => null,
            'Pending Rental Agreement' => 'Review & sign',
            'Rental Agreement Signed' => 'Pay now',
            'Occupied' => 'Rent & payments',
            'Completed' => 'View tenancy',
            'Cancelled' => null,
        ];

        foreach ($cases as $status => $label) {
            // A landlord with GCash on file, so "Pay now" (online offered) rather than "Pay your landlord".
            $t = $this->makeOnlineTenancy(['rental_status' => $status]);

            $this->assertSame($label, $t['reservation']->tenantNextStep()['label'] ?? null, $status);
        }
    }

    public function test_once_the_initial_payment_is_held_the_next_step_is_to_open_the_agreement(): void
    {
        $t = $this->makeTenancy(['rental_status' => 'Rental Agreement Signed']);
        $this->onlineInitial($t['reservation']);

        $this->assertSame('Open agreement', $t['reservation']->fresh()->tenantNextStep()['label']);
    }
}
