<?php

namespace Tests\Feature\Payments;

use App\Models\LateFeeWaiver;
use App\Models\Notification;
use App\Models\Payment;
use App\Models\PaymentClaim;
use App\Services\RentLedger;
use App\Support\LeaseTerms;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\Support\CreatesPaymentFixtures;
use Tests\TestCase;

/**
 * "Pay in full": a tenant behind by several months reports the whole balance in one go, and a landlord records it in
 * one go. The money code is the allocator that already spreads any lump of rent over months, oldest first, so these
 * tests pin what that gives for the new buttons, and that nothing about a refusal or a correction changed.
 */
class PayInFullTest extends TestCase
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

    private function claim(array $t, float $amount, string $ref = 'FULL-1', string $period = '2026-08-01')
    {
        return $this->actingAs($t['tenant'])->post(route('paymentClaims.store', $t['reservation']), [
            'accept_terms' => '1', 'claim_type' => 'Monthly', 'billing_period' => $period, 'amount' => $amount,
            'payment_method' => 'GCash', 'reference_no' => $ref, 'paid_on' => '2026-10-13',
        ]);
    }

    private function monthStatus(array $t, string $month): string
    {
        return RentLedger::for($t['reservation']->fresh())->periods()
            ->first(fn ($p) => $p['period']->format('Y-m') === $month)['status'];
    }

    public function test_the_tenant_reports_the_whole_balance_and_it_spreads_oldest_first(): void
    {
        $t = $this->behind();
        $owed = RentLedger::for($t['reservation']->fresh())->summary()['outstanding'];
        $this->assertSame(15000.0, $owed);

        $this->claim($t, $owed)->assertSessionHasNoErrors();
        $claim = PaymentClaim::firstOrFail();

        $months = $claim->monthsCovered();
        $this->assertSame(['2026-08', '2026-09', '2026-10'], array_map(fn ($m) => $m['billing_period']->format('Y-m'), $months));
        $this->assertSame('rent for Aug 2026 – Oct 2026', $claim->coverageLabel());

        $this->actingAs($t['landlord'])->post(route('landlord.paymentClaims.confirm', $claim))->assertSessionHasNoErrors();

        foreach (['2026-08', '2026-09', '2026-10'] as $month) {
            $this->assertSame('paid', $this->monthStatus($t, $month));
        }
        $this->assertSame(3, Payment::where('payment_claim_id', $claim->payment_claim_id)->count());
        $this->assertSame(0.0, RentLedger::for($t['reservation']->fresh())->summary()['outstanding']);
    }

    public function test_the_landlord_is_told_about_every_month_in_one_notification_and_the_tenant_gets_one_receipt(): void
    {
        $t = $this->behind();
        $this->claim($t, 15000);

        $told = Notification::where('user_id', $t['landlord']->user_id)->where('type', 'payment_to_confirm')->firstOrFail();
        $this->assertStringContainsString('rent for Aug 2026 – Oct 2026', $told->message);

        $this->actingAs($t['landlord'])->post(route('landlord.paymentClaims.confirm', PaymentClaim::firstOrFail()));

        $this->assertSame(1, Notification::where('user_id', $t['tenant']->user_id)->where('title', 'Payment recorded')->count());
    }

    public function test_the_landlord_records_the_full_balance_in_one_go(): void
    {
        $t = $this->behind();

        $this->actingAs($t['landlord'])->post(route('landlord.payments.store', $t['reservation']), [
            'payment_type' => 'Monthly', 'billing_period' => '2026-08-01', 'amount' => 15000,
            'payment_method' => 'Cash', 'paid_at' => '2026-10-14',
        ])->assertSessionHas('success', 'Payment recorded across 3 billing months.');

        $this->assertSame(3, Payment::where('reservation_id', $t['reservation']->reservation_id)->where('payment_type', 'Monthly')->count());
        $this->assertSame(0.0, RentLedger::for($t['reservation']->fresh())->summary()['outstanding']);
        $this->assertSame(1, Notification::where('user_id', $t['tenant']->user_id)->where('title', 'Payment recorded')->count());
    }

    public function test_a_second_report_is_refused_while_one_waits(): void
    {
        $t = $this->behind();
        $this->claim($t, 15000, 'A-1')->assertSessionHasNoErrors();

        $this->claim($t, 15000, 'A-2')->assertSessionHasErrors();

        $this->assertSame(1, PaymentClaim::count());
    }

    public function test_a_reference_that_was_already_reported_is_refused(): void
    {
        $t = $this->behind();
        $other = $this->behind();
        $this->claim($other, 15000, 'SAME-REF')->assertSessionHasNoErrors();

        $this->claim($t, 15000, 'same-ref')->assertSessionHasErrors();

        $this->assertSame(1, PaymentClaim::count());
    }

    public function test_an_amount_short_of_the_balance_stops_where_the_money_runs_out(): void
    {
        $t = $this->behind();
        $this->claim($t, 7500);

        $this->actingAs($t['landlord'])->post(route('landlord.paymentClaims.confirm', PaymentClaim::firstOrFail()));

        $periods = RentLedger::for($t['reservation']->fresh())->periods()->keyBy(fn ($p) => $p['period']->format('Y-m'));
        $this->assertSame('paid', $periods['2026-08']['status']);
        // A past-due month with part paid is still overdue, with what is left on it: September owes 2,500, October all 5,000.
        $this->assertSame(2500.0, (float) $periods['2026-09']['balance']);
        $this->assertSame(5000.0, (float) $periods['2026-10']['balance']);
        $this->assertSame(7500.0, RentLedger::for($t['reservation']->fresh())->summary()['outstanding']);
    }

    public function test_a_typed_amount_larger_than_the_balance_is_placed_by_the_server_not_trusted_per_month(): void
    {
        $t = $this->behind();
        $this->claim($t, 20000);

        $this->actingAs($t['landlord'])->post(route('landlord.paymentClaims.confirm', PaymentClaim::firstOrFail()));

        // The three owed months are settled and the extra 5,000 pays November in advance; nothing is lost or doubled.
        foreach (['2026-08', '2026-09', '2026-10'] as $month) {
            $this->assertSame('paid', $this->monthStatus($t, $month));
        }
        $this->assertSame(20000.0, (float) Payment::where('reservation_id', $t['reservation']->reservation_id)->sum('amount'));
        $this->assertSame('paid', $this->monthStatus($t, '2026-11'));
    }

    public function test_voiding_one_months_row_of_the_spread_reopens_only_that_month(): void
    {
        $t = $this->behind();
        $this->claim($t, 15000);
        $this->actingAs($t['landlord'])->post(route('landlord.paymentClaims.confirm', PaymentClaim::firstOrFail()));

        $september = Payment::where('reservation_id', $t['reservation']->reservation_id)->where('billing_period', '2026-09-01')->firstOrFail();
        $this->actingAs($t['landlord'])->post(route('landlord.payments.void', $september), ['void_reason' => 'duplicate'])->assertSessionHasNoErrors();

        $this->assertSame('paid', $this->monthStatus($t, '2026-08'));
        $this->assertNotSame('paid', $this->monthStatus($t, '2026-09'));
        $this->assertSame('paid', $this->monthStatus($t, '2026-10'));
    }

    public function test_a_late_fee_waived_after_filing_changes_where_the_money_lands_not_whether_it_is_placed(): void
    {
        $t = $this->behind();
        config(['rentals.rent_overdue_grace_days' => 0]);
        $snapshot = ['grace_days' => 0, 'late_fee' => ['type' => 'fixed', 'value' => 500]] + LeaseTerms::snapshot($t['reservation']->fresh());
        $t['reservation']->forceFill(['lease_snapshot' => $snapshot])->save();

        // With a 500 fee on each of the three months the whole balance is 16,500.
        $owed = RentLedger::for($t['reservation']->fresh())->summary()['outstanding'];
        $this->assertSame(16500.0, $owed);
        $this->claim($t, $owed)->assertSessionHasNoErrors();

        // The landlord waives August's fee before answering: that month now owes 5,000, so 500 is left over.
        LateFeeWaiver::create(['reservation_id' => $t['reservation']->reservation_id, 'billing_period' => '2026-08-01', 'waived_by' => $t['landlord']->user_id]);
        $this->actingAs($t['landlord'])->post(route('landlord.paymentClaims.confirm', PaymentClaim::firstOrFail()))->assertSessionHasNoErrors();

        foreach (['2026-08', '2026-09', '2026-10'] as $month) {
            $this->assertSame('paid', $this->monthStatus($t, $month));
        }
        // Every peso is still on the ledger: the 500 spill pays part of November rather than disappearing.
        $this->assertSame(16500.0, (float) Payment::where('reservation_id', $t['reservation']->reservation_id)->sum('amount'));
    }

    public function test_the_tenant_page_offers_pay_all_only_when_two_or_more_months_are_owed(): void
    {
        $t = $this->behind();

        $this->actingAs($t['tenant'])->get(route('tenancy.show', $t['reservation']))
            ->assertOk()
            ->assertSee('You owe 3 months')
            ->assertSee('Pay all 3 months')
            ->assertSee('15,000.00');

        $this->makePayment($t['reservation'], ['billing_period' => '2026-08-01']);
        $this->makePayment($t['reservation'], ['billing_period' => '2026-09-01']);

        $this->actingAs($t['tenant'])->get(route('tenancy.show', $t['reservation']))
            ->assertOk()
            ->assertDontSee('Pay all')
            ->assertDontSee('You owe');
    }

    public function test_the_landlord_ledger_offers_record_full_balance_only_when_two_or_more_months_are_owed(): void
    {
        $t = $this->behind();

        $this->actingAs($t['landlord'])->get(route('landlord.payments.ledger', $t['reservation']))
            ->assertOk()
            ->assertSee('Fill full balance')
            ->assertSee('15,000.00');

        $this->makePayment($t['reservation'], ['billing_period' => '2026-08-01']);
        $this->makePayment($t['reservation'], ['billing_period' => '2026-09-01']);

        $this->actingAs($t['landlord'])->get(route('landlord.payments.ledger', $t['reservation']))
            ->assertOk()
            ->assertDontSee('Fill full balance');
    }

    public function test_online_checkout_still_pays_one_month_at_a_time(): void
    {
        config(['rentals.online_payments_enabled' => true]);
        $t = $this->makeOnlineTenancy();

        $this->actingAs($t['tenant'])->get(route('tenancy.show', $t['reservation']))
            ->assertOk()
            ->assertSee('Online pays one month at a time');
        $this->assertSame('2026-08', RentLedger::for($t['reservation']->fresh())->unsettledPeriods()->first()['period']->format('Y-m'));
    }
}
