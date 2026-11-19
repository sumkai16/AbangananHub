<?php

namespace Tests\Feature\Payments;

use App\Models\Conversation;
use App\Models\Notification;
use App\Models\Payment;
use App\Models\Reservation;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\Support\CreatesPaymentFixtures;
use Tests\TestCase;

/**
 * The landlord picks a payment arrangement per unit. Rent 5,000 + deposit 5,000 = 10,000 due to move in.
 */
class ReservationArrangementTest extends TestCase
{
    use CreatesMarketplaceFixtures, CreatesPaymentFixtures, DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        config(['rentals.online_payments_enabled' => false]);
        Carbon::setTestNow('2026-10-14 10:00:00');
    }

    private function signed(array $unitAttrs = []): array
    {
        $landlord = $this->makeLandlord();
        $tenant = $this->makeTenant();
        $unit = $this->makeUnit($this->makeProperty($landlord));
        $unit->update($unitAttrs + ['rental_fee' => 5000, 'security_deposit' => 5000]);
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

        return compact('landlord', 'tenant', 'unit', 'reservation');
    }

    private function record($landlord, $reservation, float $amount)
    {
        return $this->actingAs($landlord)->post(route('landlord.reservations.recordMoveInPayment', $reservation), [
            'initial_amount' => $amount, 'payment_method' => 'Cash', 'payment_date' => '2026-10-14',
        ]);
    }

    public function test_default_arrangement_refuses_a_payment_below_what_is_due(): void
    {
        ['landlord' => $landlord, 'reservation' => $reservation] = $this->signed();

        $this->record($landlord, $reservation, 3000)->assertSessionHasErrors();

        $this->assertSame(0, Payment::where('reservation_id', $reservation->reservation_id)->count());
        $this->assertNull($reservation->fresh()->move_in_paid_at);
    }

    public function test_reservation_fee_holds_the_unit_and_the_balance_settles_the_move_in(): void
    {
        ['landlord' => $landlord, 'reservation' => $reservation, 'unit' => $unit] = $this->signed([
            'payment_arrangement' => 'reservation_fee', 'reservation_fee_amount' => 1000,
        ]);

        $this->record($landlord, $reservation, 500)->assertSessionHasErrors();
        $this->record($landlord, $reservation, 1000)->assertSessionHasNoErrors();

        $reservation->refresh();
        $this->assertSame('Reserved', $unit->fresh()->availability_status, 'A paid fee holds the unit.');
        $this->assertNull($reservation->move_in_paid_at, 'The balance is still owed.');
        $this->assertSame(9000.0, $reservation->moveInShortfall());
        $this->assertFalse($reservation->markOccupied());

        $this->record($landlord, $reservation, 9000)->assertSessionHasNoErrors();

        $reservation->refresh();
        $this->assertNotNull($reservation->move_in_paid_at);
        $rows = Payment::where('reservation_id', $reservation->reservation_id)->get();
        $this->assertSame(10000.0, round((float) $rows->sum('amount'), 2), 'Nothing is counted twice.');
        $this->assertSame(1000.0, (float) $rows->firstWhere('payment_type', 'Reservation Fee')->amount);
        $this->assertSame(4000.0, (float) $rows->firstWhere('payment_type', 'Deposit')->amount, 'The fee counts toward the deposit.');
        $this->assertTrue($reservation->markOccupied());
    }

    public function test_a_unit_deadline_replaces_the_platform_default(): void
    {
        ['reservation' => $reservation] = $this->signed(['payment_deadline_days' => 6]);

        $reservation->load('unit');
        $this->assertSame(6, $reservation->paymentDeadlineDays('signed'));
        $this->assertTrue($reservation->paymentDeadline()->isSameDay($reservation->quietSince()->copy()->addDays(6)->timezone(config('rentals.timezone'))));
    }

    public function test_pay_lines_add_up_to_the_amount_asked_for(): void
    {
        ['landlord' => $landlord, 'reservation' => $reservation] = $this->signed([
            'payment_arrangement' => 'reservation_fee', 'reservation_fee_amount' => 1000,
        ]);

        $lines = $reservation->fresh(['unit'])->moveInPayLines();
        $this->assertSame([['Reservation fee', 1000.0]], $lines);
        $this->assertSame(9000.0, $reservation->fresh(['unit'])->moveInBalanceAfterFee());

        $this->record($landlord, $reservation, 1000)->assertSessionHasNoErrors();

        $after = $reservation->fresh(['unit']);
        $this->assertSame(9000.0, round(collect($after->moveInPayLines())->sum(1), 2), 'The lines add up to the balance.');
    }

    public function test_online_checkout_is_refused_once_part_is_paid(): void
    {
        config(['rentals.online_payments_enabled' => true]);
        ['landlord' => $landlord, 'tenant' => $tenant, 'reservation' => $reservation] = $this->signed([
            'payment_arrangement' => 'reservation_fee', 'reservation_fee_amount' => 1000,
        ]);
        $this->record($landlord, $reservation, 1000)->assertSessionHasNoErrors();

        $this->actingAs($tenant)->post(route('payments.checkout', $reservation), ['accept_terms' => '1'])
            ->assertSessionHasErrors('payment');
        $this->assertSame(0, Payment::where('reservation_id', $reservation->reservation_id)->where('status', 'Pending')->count());
    }

    public function test_agreement_page_shows_the_fee_not_rent_and_deposit_before_the_fee_is_paid(): void
    {
        ['tenant' => $tenant, 'reservation' => $reservation] = $this->signed([
            'payment_arrangement' => 'reservation_fee', 'reservation_fee_amount' => 1000,
        ]);

        $this->actingAs($tenant)->get(route('agreements.show', $reservation))
            ->assertOk()
            ->assertSee('Reservation fee')
            ->assertSee('remaining');
    }

    private function feeUnit(): array
    {
        return $this->signed(['payment_arrangement' => 'reservation_fee', 'reservation_fee_amount' => 1000, 'payment_deadline_days' => 3]);
    }

    private function notes($user, string $title): int
    {
        return Notification::where('user_id', $user->user_id)->where('title', $title)->count();
    }

    public function test_a_paid_fee_is_never_auto_cancelled_but_both_sides_are_told_once_the_balance_is_overdue(): void
    {
        ['landlord' => $landlord, 'tenant' => $tenant, 'reservation' => $reservation] = $this->feeUnit();
        $this->record($landlord, $reservation, 1000)->assertSessionHasNoErrors();

        Carbon::setTestNow('2026-10-25 10:00:00');
        $this->artisan('reservations:process-stale')->assertSuccessful();

        $this->assertSame('Rental Agreement Signed', $reservation->fresh()->rental_status, 'The fee is with the landlord: no cancel.');
        $this->assertTrue($reservation->fresh(['unit'])->balanceOverdue());
        $this->assertSame(1, $this->notes($tenant, 'Balance is due'));
        $this->assertSame(1, $this->notes($landlord, 'Balance overdue'));
    }

    public function test_an_unpaid_signed_reservation_still_auto_cancels(): void
    {
        ['reservation' => $reservation] = $this->feeUnit();

        Carbon::setTestNow('2026-10-25 10:00:00');
        $this->artisan('reservations:process-stale')->assertSuccessful();

        $this->assertSame('Cancelled', $reservation->fresh()->rental_status);
    }

    public function test_landlord_settles_the_fee_after_cancelling_and_the_tenant_is_told(): void
    {
        ['landlord' => $landlord, 'tenant' => $tenant, 'reservation' => $reservation] = $this->feeUnit();
        $this->record($landlord, $reservation, 1000)->assertSessionHasNoErrors();
        $this->actingAs($landlord)->patch(route('landlord.reservations.cancel', $reservation))->assertSessionHasNoErrors();

        $reservation->refresh();
        $this->assertSame('Cancelled', $reservation->rental_status);
        $this->assertSame(1000.0, $reservation->moneyToSettle());
        $this->assertSame(1, $this->notes($landlord, 'Payment to settle'));

        $url = route('landlord.reservations.settlePayment', $reservation);
        $this->actingAs($landlord)->post($url, ['decision' => 'kept'])->assertSessionHasErrors('reservation');
        $this->actingAs($landlord)->post($url, ['decision' => 'refunded', 'amount' => 600])->assertSessionHasErrors('reservation');

        $this->actingAs($landlord)->post($url, ['decision' => 'refunded', 'amount' => 600, 'note' => 'Kept 400 for the hold'])->assertSessionHasNoErrors();

        $reservation->refresh();
        $this->assertSame('refunded', $reservation->move_in_refund_decision);
        $this->assertSame(600.0, (float) $reservation->move_in_refund_amount);
        $this->assertSame(0.0, $reservation->moneyToSettle(), 'One decision only.');
        $this->assertSame(1, $this->notes($tenant, 'Your payment was returned'));
        $this->actingAs($landlord)->post($url, ['decision' => 'kept', 'note' => 'again'])->assertSessionHasErrors('reservation');
    }

    public function test_tenant_can_ask_for_the_refund_once(): void
    {
        ['landlord' => $landlord, 'tenant' => $tenant, 'reservation' => $reservation] = $this->feeUnit();
        $this->record($landlord, $reservation, 1000)->assertSessionHasNoErrors();
        $reservation->fresh()->cancel();

        $route = route('reservations.requestRefund', $reservation);
        $this->actingAs($tenant)->post($route)->assertSessionHas('success');
        $this->actingAs($tenant)->post($route)->assertSessionHas('warning');
        $this->assertSame(1, $this->notes($landlord, 'Tenant asked for a refund'));
    }

    public function test_the_tenant_still_sees_the_pay_card_for_the_balance_after_the_fee(): void
    {
        ['landlord' => $landlord, 'tenant' => $tenant, 'reservation' => $reservation] = $this->feeUnit();
        $this->record($landlord, $reservation, 1000)->assertSessionHasNoErrors();

        $this->actingAs($tenant)->get(route('agreements.show', $reservation))
            ->assertOk()->assertSee('The balance of')->assertDontSee('Payment received');
    }
}
