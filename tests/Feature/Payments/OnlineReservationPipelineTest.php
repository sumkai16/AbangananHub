<?php

namespace Tests\Feature\Payments;

use App\Http\Controllers\Landlord\ReservationController;
use App\Models\Reservation;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\Support\CreatesPaymentFixtures;
use Tests\TestCase;

/**
 * A reservation paid online (PayMongo, money Held) moves through the landlord's pipeline the same way a
 * confirmed direct payment does: out of "Awaiting payment", holding its unit, "Awaiting move-in" on Contracts.
 * plans/axci-merge-online-reservation-payments.md.
 */
class OnlineReservationPipelineTest extends TestCase
{
    use CreatesMarketplaceFixtures, CreatesPaymentFixtures, DatabaseTransactions;

    private array $t;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-14 10:00:00');
        config(['rentals.online_payments_enabled' => true]);

        $this->t = $this->makeTenancy([
            'rental_status' => 'Rental Agreement Signed', 'agreed_at' => now(), 'target_move_in_date' => '2026-11-01',
        ]);
    }

    private function signed(): Reservation
    {
        return $this->t['reservation'];
    }

    private function payOnline(string $status = 'Held'): void
    {
        $this->makePayment($this->signed(), [
            'payment_type' => 'Initial', 'billing_period' => null, 'payment_method' => 'GCash',
            'status' => $status, 'paid_at' => now(), 'paymongo_checkout_session_id' => 'cs_'.$status,
        ]);
    }

    private function stageIds(string $stage): array
    {
        return ReservationController::applyStage(Reservation::query(), $stage)->pluck('reservation_id')->all();
    }

    public function test_an_unpaid_signed_reservation_waits_for_payment(): void
    {
        $this->assertContains($this->signed()->reservation_id, $this->stageIds('payment'));
        $this->assertFalse($this->signed()->fresh()->moveInSettled());
        $this->assertSame('Awaiting payment', $this->signed()->fresh()->contractStatus()['label']);
    }

    public function test_a_reservation_paid_online_leaves_awaiting_payment(): void
    {
        $this->payOnline();
        $reservation = $this->signed()->fresh();

        $this->assertNotContains($reservation->reservation_id, $this->stageIds('payment'));
        $this->assertNotContains($reservation->reservation_id, $this->stageIds('open'));
        $this->assertContains($reservation->reservation_id, $this->stageIds('history'));

        $this->assertTrue($reservation->moveInSettled());
        $this->assertTrue($reservation->paidOnline());
        $this->assertSame('Awaiting move-in', $reservation->contractStatus()['label']);
        $this->assertSame('Paid online', $reservation->requestStage()['label']);
    }

    public function test_a_reservation_paid_online_holds_its_unit_against_a_competing_one(): void
    {
        $this->payOnline();

        $competitor = Reservation::create([
            'tenant_id' => $this->makeTenant()->user_id, 'property_id' => $this->signed()->property_id,
            'unit_id' => $this->signed()->unit_id, 'rental_status' => 'Rental Agreement Signed',
            'reservation_date' => '2026-10-12', 'agreed_monthly_rent' => 5000,
        ]);

        $this->assertTrue($competitor->unitHeldByAnother());
    }

    public function test_a_held_payment_cannot_be_recorded_again_or_confirmed_as_a_direct_move_in(): void
    {
        $this->payOnline();
        $reservation = $this->signed()->fresh();

        $this->assertFalse($this->t['landlord']->can('recordMoveInPayment', $reservation));
        $this->assertFalse($this->t['landlord']->can('confirmDirectMoveIn', $reservation));
    }

    public function test_the_webhook_settling_the_payment_reserves_the_unit(): void
    {
        config(['services.paymongo.webhook_secret' => 'whsec_test']);
        $this->makePendingCheckout($this->signed(), 'cs_hook');
        $this->assertSame('Available', $this->signed()->unit->fresh()->availability_status);

        $body = json_encode(['data' => ['attributes' => [
            'type' => 'checkout_session.payment.paid',
            'data' => ['id' => 'cs_hook', 'attributes' => [
                'payment_intent' => ['id' => 'pi_hook'], 'payments' => [['id' => 'pay_hook']], 'payment_method_used' => 'gcash',
            ]],
        ]]]);
        $time = time();
        $signature = "t={$time},te=".hash_hmac('sha256', $time.'.'.$body, 'whsec_test').',li=';

        $this->call('POST', route('webhooks.paymongo'), [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_PAYMONGO_SIGNATURE' => $signature], $body)
            ->assertOk();

        $this->assertSame('Reserved', $this->signed()->unit->fresh()->availability_status);
    }

    public function test_the_return_page_settling_the_payment_reserves_the_unit(): void
    {
        $this->makePendingCheckout($this->signed(), 'cs_poll');
        Http::fake(['api.paymongo.com/v1/checkout_sessions/cs_poll' => Http::response(['data' => ['id' => 'cs_poll', 'attributes' => [
            'status' => 'paid', 'payment_intent' => ['id' => 'pi_poll'],
            'payments' => [['id' => 'pay_poll', 'attributes' => ['source' => ['type' => 'gcash']]]], 'payment_method_used' => 'gcash',
        ]]])]);

        $this->actingAs($this->t['tenant'])->get(route('payments.success', $this->signed()));

        $this->assertSame('Held', $this->signed()->payments()->first()->status);
        $this->assertSame('Reserved', $this->signed()->unit->fresh()->availability_status);
    }

    public function test_a_second_tenant_cannot_pay_for_a_unit_already_paid_for(): void
    {
        $this->payOnline();

        $tenant = $this->makeTenant();
        $competitor = Reservation::create([
            'tenant_id' => $tenant->user_id, 'property_id' => $this->signed()->property_id, 'unit_id' => $this->signed()->unit_id,
            'rental_status' => 'Rental Agreement Signed', 'agreed_at' => now(), 'reservation_date' => '2026-10-12', 'agreed_monthly_rent' => 5000,
        ]);
        Http::fake();

        $this->actingAs($tenant)->post(route('payments.checkout', $competitor))
            ->assertSessionHasErrors(['payment' => 'This unit is already taken by another tenant. Please contact your landlord.']);

        Http::assertNothingSent();
        $this->assertSame(0, $competitor->payments()->count());
    }

    public function test_the_contract_modal_shows_the_held_money_and_the_keys_step(): void
    {
        $this->payOnline();
        $name = $this->t['tenant']->first_name;

        $this->actingAs($this->t['landlord'])->get(route('landlord.contracts.show', $this->signed()))
            ->assertOk()
            ->assertSee('paid online · held by AbangananHub')
            ->assertSee('Mark keys turned over')
            ->assertSee(route('landlord.payments.ledger', $this->signed()), false)
            ->assertDontSee("Waiting for {$name}'s move-in payment", false)
            ->assertDontSee(route('landlord.reservations.confirmDirectMoveIn', $this->signed()), false);

        $this->actingAs($this->t['landlord'])->post(route('landlord.reservations.markTurnedOver', $this->signed()))
            ->assertSessionHas('success');

        $this->actingAs($this->t['landlord'])->get(route('landlord.contracts.show', $this->signed()))
            ->assertOk()->assertSee('Keys handed over')->assertDontSee('Mark keys turned over');
    }

    public function test_the_dashboard_asks_for_the_keys_only_when_money_is_held(): void
    {
        $name = $this->t['tenant']->first_name;

        $this->actingAs($this->t['landlord'])->get(route('landlord.dashboard'))
            ->assertOk()->assertDontSee("Turn over the keys to {$name}");

        $this->payOnline();

        $this->actingAs($this->t['landlord'])->get(route('landlord.dashboard'))
            ->assertOk()->assertSee("Turn over the keys to {$name}");
    }

    public function test_money_released_before_the_keys_can_still_be_moved_in(): void
    {
        // An admin release before the keys were handed over leaves the reservation Signed with nothing Held.
        $this->payOnline('Released');
        $reservation = $this->signed()->fresh();

        $this->assertTrue($this->t['landlord']->can('confirmDirectMoveIn', $reservation));
        $this->assertTrue($reservation->markOccupied());
        $this->assertSame('Occupied', $reservation->fresh()->rental_status);
    }
}
