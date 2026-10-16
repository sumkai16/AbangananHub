<?php

namespace Tests\Feature\Payments;

use App\Models\Notification;
use App\Models\Payment;
use App\Models\Reservation;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\Support\CreatesPaymentFixtures;
use Tests\TestCase;

/**
 * What happens when a checkout is abandoned, and the rule that money which is held (or may still be paid)
 * cannot be stranded by cancelling the reservation underneath it.
 *
 * Before this, a Pending payment was never cleared: Pay Now vanished and retrying was refused for good, and a
 * reservation with held money could be cancelled with no refund path. PayMongo is faked; nothing here hits
 * the network.
 */
class TenantPaymentRecoveryTest extends TestCase
{
    use CreatesMarketplaceFixtures, CreatesPaymentFixtures, DatabaseTransactions;

    private const BASE = 'https://api.paymongo.com/v1/checkout_sessions';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.paymongo.secret_key' => 'sk_test_x']);
        Carbon::setTestNow('2026-10-14 10:00:00');
    }

    // ─── helpers ────────────────────────────────────────────────

    private function signed(): array
    {
        // signAgreement() always stamps these; the agreement page prints them.
        return $this->makeTenancy(['rental_status' => 'Rental Agreement Signed', 'agreed_at' => now(), 'agreed_ip' => '127.0.0.1']);
    }

    private function abandoned(Reservation $reservation, string $sessionId = 'cs_old', int $minutesAgo = 45): Payment
    {
        $payment = $this->makePendingCheckout($reservation, $sessionId);
        Payment::whereKey($payment->getKey())->update(['created_at' => now()->subMinutes($minutesAgo)]);

        return $payment->fresh();
    }

    private function newSession(): array
    {
        return [self::BASE => Http::response(['data' => [
            'id'         => 'cs_new',
            'attributes' => ['checkout_url' => 'https://checkout.paymongo.test/cs_new'],
        ]], 200)];
    }

    private function sessionStatus(string $status, array $payments = []): array
    {
        return ['data' => ['attributes' => [
            'status'         => $status,
            'payments'       => $payments,
            'payment_intent' => ['id' => 'pi_1'],
        ]]];
    }

    private function postedNewSession(): bool
    {
        return Http::recorded(fn (Request $r) => $r->method() === 'POST' && $r->url() === self::BASE)->isNotEmpty();
    }

    private function pay(array $t)
    {
        return $this->actingAs($t['tenant'])->post(route('payments.checkout', $t['reservation']));
    }

    // ─── abandoned checkout ─────────────────────────────────────

    public function test_an_abandoned_checkout_that_paymongo_expires_lets_the_tenant_pay_again(): void
    {
        $t = $this->signed();
        $old = $this->abandoned($t['reservation']);

        Http::fake([
            self::BASE.'/cs_old/expire' => Http::response(['data' => ['id' => 'cs_old']], 200),
            self::BASE.'/cs_old'        => Http::response($this->sessionStatus('active'), 200),
        ] + $this->newSession());

        $this->pay($t)->assertRedirect('https://checkout.paymongo.test/cs_new');

        $this->assertNull(Payment::find($old->getKey()), 'the dead placeholder is gone');
        $this->assertTrue(
            Payment::where('reservation_id', $t['reservation']->reservation_id)
                ->where('paymongo_checkout_session_id', 'cs_new')->where('status', 'Pending')->exists()
        );
        Http::assertSent(fn (Request $r) => $r->method() === 'POST' && $r->url() === self::BASE.'/cs_old/expire');
    }

    public function test_an_abandoned_checkout_that_cannot_be_expired_blocks_a_second_session(): void
    {
        $t = $this->signed();
        $old = $this->abandoned($t['reservation']);

        // Fail safe: if we cannot prove the old session is dead it might still pay, and two live sessions is a double charge.
        Http::fake([
            self::BASE.'/cs_old/expire' => Http::response(['errors' => [['detail' => 'nope']]], 500),
            self::BASE.'/cs_old'        => Http::response($this->sessionStatus('active'), 200),
        ] + $this->newSession());

        $this->pay($t)->assertSessionHasErrors('payment');

        $this->assertFalse($this->postedNewSession(), 'no second session was opened');
        $this->assertNotNull(Payment::find($old->getKey()), 'the old row is kept');
    }

    public function test_an_abandoned_checkout_that_was_actually_paid_is_settled_not_replaced(): void
    {
        $t = $this->signed();
        $old = $this->abandoned($t['reservation']);

        Http::fake([
            self::BASE.'/cs_old' => Http::response($this->sessionStatus('paid', [['id' => 'pay_1', 'attributes' => ['source' => ['type' => 'gcash']]]]), 200),
        ] + $this->newSession());

        $this->pay($t)->assertSessionHasErrors('payment');

        $this->assertSame('Held', $old->fresh()->status, 'the money that arrived is recorded');
        $this->assertFalse($this->postedNewSession());
    }

    public function test_a_recent_pending_checkout_is_left_alone_and_still_blocks(): void
    {
        $t = $this->signed();
        $this->abandoned($t['reservation'], 'cs_recent', minutesAgo: 5);

        Http::fake($this->newSession());

        $this->pay($t)->assertSessionHasErrors('payment');

        $this->assertFalse($this->postedNewSession());
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), '/expire'));
    }

    public function test_a_placeholder_that_never_got_a_session_id_is_cleared_after_five_minutes(): void
    {
        $t = $this->signed();
        $dead = $this->makePendingCheckout($t['reservation'], 'x', ['paymongo_checkout_session_id' => null]);
        Payment::whereKey($dead->getKey())->update(['created_at' => now()->subMinutes(10)]);

        Http::fake($this->newSession());

        $this->pay($t)->assertRedirect('https://checkout.paymongo.test/cs_new');

        $this->assertNull(Payment::find($dead->getKey()));
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), '/expire'));
    }

    public function test_the_agreement_page_offers_pay_now_again_for_an_abandoned_attempt(): void
    {
        $t = $this->signed();
        $this->abandoned($t['reservation']);

        $html = $this->actingAs($t['tenant'])->get(route('agreements.show', $t['reservation']))->assertOk()->getContent();

        $this->assertStringContainsString('Pay Now', $html);
        $this->assertStringContainsString("last payment attempt wasn't completed", $html);
    }

    public function test_the_agreement_page_shows_a_payment_error_instead_of_nothing(): void
    {
        $t = $this->signed();

        $html = $this->actingAs($t['tenant'])
            ->withSession(['errors' => (new \Illuminate\Support\ViewErrorBag)->put('default', new \Illuminate\Support\MessageBag(['payment' => ['Could not create payment session. Please try again.']]))])
            ->get(route('agreements.show', $t['reservation']))
            ->assertOk()->getContent();

        $this->assertStringContainsString('Could not create payment session', $html);
    }

    // ─── cancel / reject cannot strand money ────────────────────

    public function test_cancel_is_refused_while_a_payment_is_held(): void
    {
        $t = $this->signed();
        $this->makePayment($t['reservation'], ['payment_type' => 'Initial', 'billing_period' => null, 'status' => 'Held', 'payment_method' => 'GCash', 'amount' => 5000]);

        $this->actingAs($t['tenant'])->patch(route('reservations.cancel', $t['reservation']))
            ->assertSessionHas('error', fn ($msg) => str_contains($msg, 'held by AbangananHub'));

        $this->assertSame('Rental Agreement Signed', $t['reservation']->fresh()->rental_status);
    }

    public function test_cancel_is_refused_while_a_checkout_is_open(): void
    {
        $t = $this->signed();
        $this->makePendingCheckout($t['reservation'], 'cs_open');

        $this->actingAs($t['tenant'])->patch(route('reservations.cancel', $t['reservation']))
            ->assertSessionHas('error', fn ($msg) => str_contains($msg, 'being processed'));

        $this->assertSame('Rental Agreement Signed', $t['reservation']->fresh()->rental_status);
    }

    public function test_cancel_works_when_nothing_is_held(): void
    {
        $t = $this->signed();

        $this->actingAs($t['tenant'])->patch(route('reservations.cancel', $t['reservation']))
            ->assertSessionHas('success');

        $this->assertSame('Cancelled', $t['reservation']->fresh()->rental_status);
    }

    public function test_an_abandoned_checkout_does_not_block_cancel(): void
    {
        $t = $this->signed();
        $this->abandoned($t['reservation']);

        $this->actingAs($t['tenant'])->patch(route('reservations.cancel', $t['reservation']))
            ->assertSessionHas('success');

        $this->assertSame('Cancelled', $t['reservation']->fresh()->rental_status);
    }

    public function test_the_landlord_cannot_reject_a_reservation_that_holds_money(): void
    {
        $t = $this->signed();
        $this->makePayment($t['reservation'], ['payment_type' => 'Initial', 'billing_period' => null, 'status' => 'Held', 'payment_method' => 'GCash', 'amount' => 5000]);

        $this->actingAs($t['landlord'])->patch(route('landlord.reservations.reject', $t['reservation']))
            ->assertSessionHasErrors('reservation');

        $this->assertSame('Rental Agreement Signed', $t['reservation']->fresh()->rental_status);
    }

    public function test_the_reservations_list_hides_cancel_and_says_why_when_money_is_held(): void
    {
        $t = $this->signed();
        $this->makePayment($t['reservation'], ['payment_type' => 'Initial', 'billing_period' => null, 'status' => 'Held', 'payment_method' => 'GCash', 'amount' => 5000]);

        $html = $this->actingAs($t['tenant'])->get(route('reservations.index'))->assertOk()->getContent();

        $this->assertStringContainsString('held by AbangananHub', $html);
        $this->assertStringNotContainsString(route('reservations.cancel', $t['reservation']), $html, 'no Cancel form is offered');
        $this->assertStringContainsString('Open agreement', $html, 'the next step is offered instead');
    }

    public function test_the_landlord_list_explains_why_cancel_is_missing_when_money_is_held(): void
    {
        $t = $this->signed();
        $this->makePayment($t['reservation'], ['payment_type' => 'Initial', 'billing_period' => null, 'status' => 'Held', 'payment_method' => 'GCash', 'amount' => 5000]);

        $html = $this->actingAs($t['landlord'])->get(route('landlord.reservations.index'))->assertOk()->getContent();

        $this->assertStringContainsString('held by AbangananHub', $html);
    }

    // ─── money that arrives after the reservation ended ─────────

    public function test_money_landing_on_a_cancelled_reservation_alerts_the_admins(): void
    {
        $admin = $this->makeUserWithRole('Admin');
        $t = $this->makeTenancy(['rental_status' => 'Cancelled']);
        $payment = $this->makePayment($t['reservation'], ['payment_type' => 'Initial', 'billing_period' => null, 'status' => 'Held', 'payment_method' => 'GCash', 'amount' => 5000]);

        $payment->alertAdminsIfStranded();

        $this->assertTrue(
            Notification::where('user_id', $admin->user_id)->where('title', 'Payment received for an ended reservation')->exists()
        );
    }

    public function test_money_on_a_live_reservation_alerts_nobody(): void
    {
        $admin = $this->makeUserWithRole('Admin');
        $t = $this->signed();
        $payment = $this->makePayment($t['reservation'], ['payment_type' => 'Initial', 'billing_period' => null, 'status' => 'Held', 'payment_method' => 'GCash', 'amount' => 5000]);

        $payment->alertAdminsIfStranded();

        $this->assertFalse(Notification::where('user_id', $admin->user_id)->where('title', 'Payment received for an ended reservation')->exists());
    }

    public function test_admin_can_still_force_cancel_a_reservation_that_holds_money(): void
    {
        $admin = $this->makeUserWithRole('Admin');
        $t = $this->signed();
        $this->makePayment($t['reservation'], ['payment_type' => 'Initial', 'billing_period' => null, 'status' => 'Held', 'payment_method' => 'GCash', 'amount' => 5000]);

        $this->actingAs($admin)->patch(route('admin.reservations.forceCancel', $t['reservation']))->assertRedirect();

        $this->assertSame('Cancelled', $t['reservation']->fresh()->rental_status);
    }
}
