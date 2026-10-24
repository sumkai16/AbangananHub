<?php

namespace Tests\Feature\Payments;

use App\Models\Conversation;
use App\Models\Payment;
use App\Models\PaymentClaim;
use App\Models\Reservation;
use App\Services\RentLedger;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\Support\CreatesPaymentFixtures;
use Tests\TestCase;

/**
 * Paying the landlord directly: the tenant reports a payment, the landlord confirms it, and only then does
 * it count. Nothing here is held, so no confirmed row may ever carry payout_owed_at.
 */
class PaymentClaimTest extends TestCase
{
    use CreatesMarketplaceFixtures, CreatesPaymentFixtures, DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        config(['rentals.online_payments_enabled' => false]);
        Carbon::setTestNow('2026-10-14 10:00:00');
    }

    /** A signed agreement waiting for the first payment (rent 5,000 + deposit 5,000). */
    private function signedReservation(): array
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
            'conversation_id' => $conversation->conversation_id,
            'rental_status' => 'Rental Agreement Signed', 'reservation_date' => '2026-10-01',
            'target_move_in_date' => '2026-10-15', 'agreed_monthly_rent' => 5000, 'rent_due_day' => 15,
        ]);

        return compact('landlord', 'tenant', 'reservation', 'unit');
    }

    private function moveInClaim(array $overrides = []): array
    {
        return $overrides + [
            'claim_type' => 'MoveIn', 'amount' => 10000, 'payment_method' => 'GCash',
            'reference_no' => 'GC123456', 'paid_on' => '2026-10-13',
        ];
    }

    public function test_tenant_reports_move_in_money_and_landlord_confirms_it(): void
    {
        ['landlord' => $landlord, 'tenant' => $tenant, 'reservation' => $reservation] = $this->signedReservation();

        $this->actingAs($tenant)
            ->post(route('paymentClaims.store', $reservation), $this->moveInClaim())
            ->assertSessionHasNoErrors();

        $claim = PaymentClaim::firstOrFail();
        $this->assertSame('Pending', $claim->status);
        $this->assertFalse($reservation->fresh()->hasSettledInitialPayment(), 'A report is not money.');
        $this->assertSame(0, Payment::where('reservation_id', $reservation->reservation_id)->count());

        $this->actingAs($landlord)
            ->post(route('landlord.paymentClaims.confirm', $claim))
            ->assertSessionHasNoErrors();

        $this->assertSame('Confirmed', $claim->fresh()->status);
        $reservation->refresh();
        $this->assertNotNull($reservation->move_in_paid_at);
        $this->assertTrue($reservation->hasSettledInitialPayment());

        $rows = Payment::where('reservation_id', $reservation->reservation_id)->get();
        $this->assertEqualsCanonicalizing(['Deposit', 'Monthly'], $rows->pluck('payment_type')->all());
        $this->assertSame([$claim->payment_claim_id], $rows->pluck('payment_claim_id')->unique()->values()->all());
        $this->assertTrue($rows->every(fn ($p) => $p->status === 'Paid' && $p->payout_owed_at === null));

        $october = RentLedger::for($reservation)->periods()->firstWhere(fn ($p) => $p['period']->isSameMonth(Carbon::parse('2026-10-01')));
        $this->assertSame('paid', $october['status'], 'Month one must read paid, not due.');
    }

    public function test_either_party_confirms_move_in_once_the_payment_is_settled(): void
    {
        foreach (['tenant', 'landlord'] as $who) {
            ['landlord' => $landlord, 'tenant' => $tenant, 'reservation' => $reservation, 'unit' => $unit] = $this->signedReservation();
            $reservation->update(['move_in_paid_at' => now()]);
            $actor = $who === 'tenant' ? $tenant : $landlord;
            $route = $who === 'tenant' ? route('agreements.confirmDirectMoveIn', $reservation) : route('landlord.reservations.confirmDirectMoveIn', $reservation);

            $this->actingAs($actor)->post($route)->assertSessionHasNoErrors();

            $this->assertSame('Occupied', $reservation->fresh()->rental_status);
            $this->assertSame('Occupied', $unit->fresh()->availability_status);
            $this->assertNotNull(Conversation::where('reservation_id', $reservation->reservation_id)->where('type', 'tenancy')->first(), 'The tenancy chat opens.');
            $this->assertSame('Resolved', Conversation::find($reservation->conversation_id)->status, 'The inquiry thread closes once the tenancy chat opens.');

            $this->actingAs($tenant)->get(route('home'))->assertSee('My Unit', false)->assertSee(route('tenancy.show', $reservation), false);
        }
    }

    public function test_move_in_cannot_be_confirmed_before_the_payment_is_settled(): void
    {
        ['tenant' => $tenant, 'reservation' => $reservation] = $this->signedReservation();

        $this->actingAs($tenant)->post(route('agreements.confirmDirectMoveIn', $reservation))->assertForbidden();
        $this->assertSame('Rental Agreement Signed', $reservation->fresh()->rental_status);
    }

    public function test_a_double_confirm_writes_one_set_of_rows(): void
    {
        ['landlord' => $landlord, 'tenant' => $tenant, 'reservation' => $reservation] = $this->signedReservation();
        $this->actingAs($tenant)->post(route('paymentClaims.store', $reservation), $this->moveInClaim());
        $claim = PaymentClaim::firstOrFail();

        $this->actingAs($landlord)->post(route('landlord.paymentClaims.confirm', $claim));
        $this->actingAs($landlord)->post(route('landlord.paymentClaims.confirm', $claim))->assertSessionHasErrors('payment');

        $this->assertSame(2, Payment::where('reservation_id', $reservation->reservation_id)->count());
    }

    public function test_reject_needs_a_reason_and_the_tenant_can_report_again(): void
    {
        ['landlord' => $landlord, 'tenant' => $tenant, 'reservation' => $reservation] = $this->signedReservation();
        $this->actingAs($tenant)->post(route('paymentClaims.store', $reservation), $this->moveInClaim());
        $claim = PaymentClaim::firstOrFail();

        $this->actingAs($landlord)->post(route('landlord.paymentClaims.reject', $claim), [])->assertSessionHasErrors('rejection_reason');
        $this->actingAs($landlord)->post(route('landlord.paymentClaims.reject', $claim), ['rejection_reason' => 'No payment with that reference.']);

        $this->assertSame('Rejected', $claim->fresh()->status);
        $this->assertSame(0, Payment::where('reservation_id', $reservation->reservation_id)->count());

        $this->actingAs($tenant)->post(route('paymentClaims.store', $reservation), $this->moveInClaim(['reference_no' => 'GC999']))
            ->assertSessionHasNoErrors();
        $this->assertSame(2, PaymentClaim::count());
    }

    public function test_only_one_pending_report_per_payment_and_the_tenant_can_withdraw_it(): void
    {
        ['tenant' => $tenant, 'reservation' => $reservation] = $this->signedReservation();
        $this->actingAs($tenant)->post(route('paymentClaims.store', $reservation), $this->moveInClaim());

        $this->actingAs($tenant)->post(route('paymentClaims.store', $reservation), $this->moveInClaim(['reference_no' => 'AGAIN']))
            ->assertSessionHasErrors('payment');

        $claim = PaymentClaim::firstOrFail();
        $this->actingAs($tenant)->delete(route('paymentClaims.withdraw', $claim))->assertSessionHasNoErrors();
        $this->assertSame('Withdrawn', $claim->fresh()->status);
    }

    public function test_a_reference_number_is_required(): void
    {
        ['tenant' => $tenant, 'reservation' => $reservation] = $this->signedReservation();

        $this->actingAs($tenant)->post(route('paymentClaims.store', $reservation), $this->moveInClaim(['reference_no' => '']))
            ->assertSessionHasErrors('reference_no');
    }

    public function test_strangers_cannot_file_confirm_or_withdraw(): void
    {
        ['landlord' => $landlord, 'tenant' => $tenant, 'reservation' => $reservation] = $this->signedReservation();
        $otherTenant = $this->makeTenant();
        $otherLandlord = $this->makeLandlord();

        $this->actingAs($otherTenant)->post(route('paymentClaims.store', $reservation), $this->moveInClaim())->assertForbidden();

        $this->actingAs($tenant)->post(route('paymentClaims.store', $reservation), $this->moveInClaim());
        $claim = PaymentClaim::firstOrFail();

        $this->actingAs($otherLandlord)->post(route('landlord.paymentClaims.confirm', $claim))->assertForbidden();
        $this->actingAs($tenant)->post(route('landlord.paymentClaims.confirm', $claim))->assertForbidden();
        $this->actingAs($otherTenant)->delete(route('paymentClaims.withdraw', $claim))->assertForbidden();
        $this->assertSame('Pending', $claim->fresh()->status);
    }

    public function test_landlord_recording_move_in_money_withdraws_a_pending_report(): void
    {
        ['landlord' => $landlord, 'tenant' => $tenant, 'reservation' => $reservation] = $this->signedReservation();
        $this->actingAs($tenant)->post(route('paymentClaims.store', $reservation), $this->moveInClaim());

        $this->actingAs($landlord)->post(route('landlord.reservations.recordMoveInPayment', $reservation), [
            'initial_amount' => 10000, 'payment_method' => 'Cash', 'payment_date' => '2026-10-14',
        ])->assertSessionHasNoErrors();

        $this->assertSame('Withdrawn', PaymentClaim::firstOrFail()->status);
        $this->assertNotNull($reservation->fresh()->move_in_paid_at);
        $this->assertSame(2, Payment::where('reservation_id', $reservation->reservation_id)->count());
    }

    public function test_a_monthly_report_confirms_into_the_right_months_and_judges_lateness_by_the_payment_date(): void
    {
        // Rent 5,000 due on the 5th, grace 0: September's deadline is Sep 5 end of day.
        ['landlord' => $landlord, 'tenant' => $tenant, 'reservation' => $reservation] = $this->makeTenancy(['rent_due_day' => 5, 'target_move_in_date' => '2026-09-01', 'reservation_date' => '2026-09-01']);
        $reservation->update(['lease_snapshot' => ['grace_days' => 0, 'late_fee' => ['type' => 'fixed', 'value' => 500]]]);

        // Paid on time (Sep 4) but the landlord only confirms on Oct 14.
        $this->actingAs($tenant)->post(route('paymentClaims.store', $reservation), [
            'claim_type' => 'Monthly', 'billing_period' => '2026-09-01', 'amount' => 5000,
            'payment_method' => 'Maya', 'reference_no' => 'MY777', 'paid_on' => '2026-09-04',
        ])->assertSessionHasNoErrors();

        $this->actingAs($landlord)->post(route('landlord.paymentClaims.confirm', PaymentClaim::firstOrFail()))->assertSessionHasNoErrors();

        $september = RentLedger::for($reservation->fresh())->periods()->firstWhere(fn ($p) => $p['period']->isSameMonth(Carbon::parse('2026-09-01')));
        $this->assertSame(0.0, (float) $september['late_fee'], 'Paid on the 4th, so no late fee even though it was confirmed in October.');
        $this->assertSame('paid', $september['status']);
        $this->assertNull(Payment::where('payment_claim_id', PaymentClaim::first()->payment_claim_id)->first()->payout_owed_at);
    }

    public function test_a_tenant_who_is_paid_up_can_report_rent_ahead_and_it_covers_the_next_months(): void
    {
        ['landlord' => $landlord, 'tenant' => $tenant, 'reservation' => $reservation] = $this->makeTenancy(['rent_due_day' => 5, 'target_move_in_date' => '2026-10-01', 'reservation_date' => '2026-10-01']);
        $this->makePayment($reservation, ['billing_period' => '2026-10-01']);

        $this->assertNull(RentLedger::for($reservation->fresh())->unsettledPeriods()->first());
        $advance = RentLedger::for($reservation->fresh())->advancePeriod();
        $this->assertSame('2026-11', $advance['period']->format('Y-m'));

        // Two months at once: the lump spreads across November and December.
        $this->actingAs($tenant)->post(route('paymentClaims.store', $reservation), [
            'claim_type' => 'Monthly', 'billing_period' => '2026-11-01', 'amount' => 10000,
            'payment_method' => 'GCash', 'reference_no' => 'ADV1', 'paid_on' => '2026-10-14',
        ])->assertSessionHasNoErrors();

        $this->actingAs($landlord)->post(route('landlord.paymentClaims.confirm', PaymentClaim::firstOrFail()))->assertSessionHasNoErrors();

        $periods = RentLedger::for($reservation->fresh())->periods();
        $this->assertSame('paid', $periods->firstWhere(fn ($p) => $p['period']->format('Y-m') === '2026-11')['status']);
        $this->assertSame('paid', $periods->firstWhere(fn ($p) => $p['period']->format('Y-m') === '2026-12')['status']);
    }

    public function test_a_tenant_cannot_skip_ahead_while_a_month_is_still_unpaid(): void
    {
        ['tenant' => $tenant, 'reservation' => $reservation] = $this->makeTenancy(['rent_due_day' => 5, 'target_move_in_date' => '2026-09-01', 'reservation_date' => '2026-09-01']);

        $this->assertNull(RentLedger::for($reservation->fresh())->advancePeriod());

        $this->actingAs($tenant)->post(route('paymentClaims.store', $reservation), [
            'claim_type' => 'Monthly', 'billing_period' => '2026-11-01', 'amount' => 5000,
            'payment_method' => 'GCash', 'reference_no' => 'SKIP', 'paid_on' => '2026-10-14',
        ])->assertSessionHasErrors();
        $this->assertSame(0, PaymentClaim::count());
    }

    public function test_an_already_paid_month_cannot_be_reported(): void
    {
        ['tenant' => $tenant, 'reservation' => $reservation] = $this->makeTenancy();
        $this->makePayment($reservation, ['billing_period' => '2026-08-01']);

        $this->actingAs($tenant)->post(route('paymentClaims.store', $reservation), [
            'claim_type' => 'Monthly', 'billing_period' => '2026-08-01', 'amount' => 5000,
            'payment_method' => 'GCash', 'reference_no' => 'X1', 'paid_on' => '2026-08-03',
        ])->assertSessionHasErrors('payment');
    }

    public function test_pending_reports_are_withdrawn_when_the_reservation_is_cancelled(): void
    {
        ['tenant' => $tenant, 'reservation' => $reservation] = $this->signedReservation();
        $this->actingAs($tenant)->post(route('paymentClaims.store', $reservation), $this->moveInClaim());

        $reservation->refresh()->cancel();

        $this->assertSame('Withdrawn', PaymentClaim::firstOrFail()->status);
    }

    public function test_a_report_is_refused_while_a_fresh_online_checkout_is_open(): void
    {
        ['tenant' => $tenant, 'reservation' => $reservation] = $this->signedReservation();
        $this->makePendingCheckout($reservation, 'cs_open', ['payment_type' => 'Initial']);

        $this->actingAs($tenant)->post(route('paymentClaims.store', $reservation), $this->moveInClaim())->assertSessionHasErrors('payment');
    }
}
