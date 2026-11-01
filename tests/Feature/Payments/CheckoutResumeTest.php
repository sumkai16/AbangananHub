<?php

namespace Tests\Feature\Payments;

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
 * A tenant who closed the PayMongo page must not be stuck on "Payment in progress" until the checkout expires on
 * its own (30 minutes): they can go back to the same checkout, check whether it was paid, or cancel it and choose
 * again. Whatever they press, money is never lost and a paid checkout is never thrown away.
 */
class CheckoutResumeTest extends TestCase
{
    use CreatesMarketplaceFixtures, CreatesPaymentFixtures, DatabaseTransactions;

    private const SESSION = 'cs_resume';

    private const BASE = 'api.paymongo.com/v1/checkout_sessions/'.self::SESSION;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.paymongo.secret_key' => 'sk_test_x', 'rentals.online_payments_enabled' => true]);
        Carbon::setTestNow('2026-10-14 10:00:00');
    }

    /** @return array{landlord: \App\Models\User, tenant: \App\Models\User, reservation: Reservation, payment: Payment} */
    private function signedWithOpenCheckout(): array
    {
        $t = $this->makeOnlineTenancy(['rental_status' => 'Rental Agreement Signed', 'agreed_at' => now(), 'agreed_ip' => '127.0.0.1']);
        $t['payment'] = $this->makePendingCheckout($t['reservation'], self::SESSION, ['amount' => 5000]);
        // The deposit row of the same checkout.
        $this->makePendingCheckout($t['reservation'], self::SESSION, ['payment_type' => 'Deposit', 'amount' => 3000]);

        return $t;
    }

    private function fakeSession(string $status, ?string $checkoutUrl = 'https://checkout.paymongo.test/resume', array $payments = []): void
    {
        Http::fake([
            self::BASE => Http::response(['data' => ['id' => self::SESSION, 'attributes' => [
                'status' => $status, 'checkout_url' => $checkoutUrl, 'payments' => $payments,
            ]]], 200),
            self::BASE.'/expire' => Http::response(['data' => []], 200),
        ]);
    }

    private function paidPayments(): array
    {
        return [['id' => 'pay_resume', 'attributes' => ['status' => 'paid', 'source' => ['type' => 'gcash']]]];
    }

    private function rowsLeft(Reservation $reservation): int
    {
        return Payment::where('reservation_id', $reservation->reservation_id)->where('paymongo_checkout_session_id', self::SESSION)->count();
    }

    // ─── the page ───────────────────────────────────────────────

    public function test_the_card_offers_all_three_ways_out(): void
    {
        $t = $this->signedWithOpenCheckout();

        $this->actingAs($t['tenant'])->get(route('agreements.show', $t['reservation']))
            ->assertOk()
            ->assertSee('Payment in progress')
            ->assertSee('Continue to payment')
            ->assertSee('check now')
            ->assertSee('Cancel payment')
            ->assertDontSee('How do you want to pay');

        // Continue opens PayMongo in a new tab so this page stays put.
        $html = $this->actingAs($t['tenant'])->get(route('agreements.show', $t['reservation']))->getContent();
        $this->assertMatchesRegularExpression('/<form[^>]*action="[^"]*payment\/continue"[^>]*target="_blank"/', $html);
    }

    public function test_an_open_rent_checkout_shows_the_same_card_on_the_rent_page(): void
    {
        $t = $this->makeOnlineTenancy();
        $this->makePendingCheckout($t['reservation'], 'cs_rent_open', ['payment_type' => 'Monthly', 'billing_period' => '2026-08-01']);

        $this->actingAs($t['tenant'])->get(route('tenancy.show', $t['reservation']))
            ->assertOk()->assertSee('Payment in progress')->assertSee('Continue to payment')->assertDontSee('How do you want to pay');
    }

    // ─── continue ───────────────────────────────────────────────

    public function test_continue_sends_the_tenant_back_to_the_same_checkout(): void
    {
        $this->fakeSession('active');
        $t = $this->signedWithOpenCheckout();

        $this->actingAs($t['tenant'])->post(route('payments.continue', $t['reservation']), ['type' => 'Initial'])
            ->assertRedirect('https://checkout.paymongo.test/resume');

        $this->assertSame(2, $this->rowsLeft($t['reservation']), 'The checkout is untouched, so no second session is opened.');
        Http::assertNotSent(fn (Request $r) => $r->method() === 'POST');
    }

    public function test_continue_settles_a_checkout_that_was_paid_after_all(): void
    {
        $this->fakeSession('active', null, $this->paidPayments());
        $t = $this->signedWithOpenCheckout();

        $this->actingAs($t['tenant'])->post(route('payments.continue', $t['reservation']), ['type' => 'Initial'])
            ->assertRedirect(route('payments.success', $t['reservation']));

        $this->assertSame(['Held', 'Held'], Payment::where('paymongo_checkout_session_id', self::SESSION)->orderBy('payment_id')->pluck('status')->all());
    }

    public function test_continue_on_an_expired_checkout_clears_it_so_the_tenant_can_choose_again(): void
    {
        $this->fakeSession('expired', null);
        $t = $this->signedWithOpenCheckout();

        $this->actingAs($t['tenant'])->post(route('payments.continue', $t['reservation']), ['type' => 'Initial'])
            ->assertSessionHas('warning', fn ($m) => str_contains($m, 'window has closed'));

        $this->assertSame(0, $this->rowsLeft($t['reservation']));
    }

    public function test_continue_when_paymongo_cannot_be_read_changes_nothing(): void
    {
        Http::fake([self::BASE => Http::response([], 500)]);
        $t = $this->signedWithOpenCheckout();

        $this->actingAs($t['tenant'])->post(route('payments.continue', $t['reservation']), ['type' => 'Initial'])
            ->assertSessionHasErrors('payment');

        $this->assertSame(2, $this->rowsLeft($t['reservation']));
    }

    // ─── cancel ─────────────────────────────────────────────────

    public function test_cancel_closes_the_checkout_and_drops_every_row_of_it(): void
    {
        $this->fakeSession('active');
        $t = $this->signedWithOpenCheckout();

        $this->actingAs($t['tenant'])->post(route('payments.cancel', $t['reservation']), ['type' => 'Initial'])
            ->assertSessionHas('success', fn ($m) => str_contains($m, 'Payment cancelled'));

        $this->assertSame(0, $this->rowsLeft($t['reservation']), 'Rent and deposit placeholders both go.');
        Http::assertSent(fn (Request $r) => $r->method() === 'POST' && str_ends_with($r->url(), self::SESSION.'/expire'));
    }

    public function test_cancel_keeps_everything_when_paymongo_cannot_confirm_the_session_is_closed(): void
    {
        Http::fake([
            self::BASE.'/expire' => Http::response([], 500),
            self::BASE => Http::response(['data' => ['attributes' => ['status' => 'active', 'payments' => []]]], 200),
        ]);
        $t = $this->signedWithOpenCheckout();

        $this->actingAs($t['tenant'])->post(route('payments.cancel', $t['reservation']), ['type' => 'Initial'])
            ->assertSessionHasErrors('payment');

        $this->assertSame(2, $this->rowsLeft($t['reservation']), 'A session that may still be paid is never deleted.');
    }

    public function test_cancel_never_throws_away_a_checkout_that_was_paid(): void
    {
        $this->fakeSession('active', null, $this->paidPayments());
        $t = $this->signedWithOpenCheckout();

        $this->actingAs($t['tenant'])->post(route('payments.cancel', $t['reservation']), ['type' => 'Initial'])
            ->assertSessionHasErrors('payment');

        $this->assertSame(['Held', 'Held'], Payment::where('paymongo_checkout_session_id', self::SESSION)->orderBy('payment_id')->pluck('status')->all());
    }

    public function test_cancel_works_for_an_open_rent_checkout(): void
    {
        $this->fakeSession('active');
        $t = $this->makeOnlineTenancy();
        $this->makePendingCheckout($t['reservation'], self::SESSION, ['payment_type' => 'Monthly', 'billing_period' => '2026-08-01']);

        $this->actingAs($t['tenant'])->post(route('payments.cancel', $t['reservation']), ['type' => 'Monthly'])->assertSessionHas('success');

        $this->assertSame(0, $this->rowsLeft($t['reservation']));
    }

    // ─── who may, and when there is nothing open ────────────────

    public function test_only_the_reservations_own_tenant_can_continue_or_cancel(): void
    {
        Http::fake();
        $t = $this->signedWithOpenCheckout();

        foreach (['payments.continue', 'payments.cancel'] as $route) {
            $this->actingAs($this->makeTenant())->post(route($route, $t['reservation']), ['type' => 'Initial'])->assertForbidden();
            $this->actingAs($t['landlord'])->post(route($route, $t['reservation']), ['type' => 'Initial'])->assertForbidden();
        }

        Http::assertNothingSent();
        $this->assertSame(2, $this->rowsLeft($t['reservation']));
    }

    public function test_with_nothing_open_both_say_so_and_ask_paymongo_nothing(): void
    {
        Http::fake();
        $t = $this->makeOnlineTenancy(['rental_status' => 'Rental Agreement Signed', 'agreed_at' => now(), 'agreed_ip' => '127.0.0.1']);

        $this->actingAs($t['tenant'])->post(route('payments.continue', $t['reservation']), ['type' => 'Initial'])->assertSessionHasErrors('payment');
        $this->actingAs($t['tenant'])->post(route('payments.cancel', $t['reservation']), ['type' => 'Initial'])->assertSessionHas('warning');

        Http::assertNothingSent();
    }
}
