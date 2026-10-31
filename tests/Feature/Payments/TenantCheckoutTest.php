<?php

namespace Tests\Feature\Payments;

use App\Models\Payment;
use App\Models\Reservation;
use App\Models\User;
use App\Services\PaymentClaims;
use App\Support\Payments;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\Support\CreatesPaymentFixtures;
use Tests\TestCase;

/**
 * How a tenant STARTS an online payment: the initial (escrow) checkout and the
 * monthly-rent checkout, on both the web routes and the mobile API twins.
 *
 * Pins four things, because each one costs real pesos if it slips:
 *   - only the reservation's own tenant can open a PayMongo session for it, and
 *     a refused request never reaches PayMongo at all;
 *   - the state guards (signed agreement, no second live session, Occupied
 *     tenancy, something actually owed);
 *   - the centavo amount sent to PayMongo is the amount the server computes
 *     (unit rental fee / ledger balance), never anything the client posts;
 *   - a PayMongo outage leaves no dead Pending row behind to block the retry.
 *
 * PayMongo is faked with Http::fake(); nothing here touches the network.
 * Settling a payment (the poll fallback) lives in TenantReconcileTest.
 */
class TenantCheckoutTest extends TestCase
{
    use CreatesMarketplaceFixtures, CreatesPaymentFixtures, DatabaseTransactions;

    private const CHECKOUT_ENDPOINT = 'https://api.paymongo.com/v1/checkout_sessions';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.paymongo.secret_key' => 'sk_test_x']);

        // Fixed clock: the rent ledger bills Aug, Sep and Oct 2026 for the
        // default fixture tenancy (move-in 2026-08-01), oldest first.
        Carbon::setTestNow('2026-10-14 10:00:00');
    }

    /** @return array<string, array{string}> */
    public static function surfaces(): array
    {
        return ['web' => ['web'], 'api' => ['api']];
    }

    // ─── helpers ────────────────────────────────────────────────

    private function fakeCheckoutCreated(string $sessionId = 'cs_checkout_new'): void
    {
        Http::fake([
            'api.paymongo.com/v1/checkout_sessions' => Http::response(['data' => [
                'id'         => $sessionId,
                'attributes' => ['checkout_url' => 'https://checkout.paymongo.test/' . $sessionId],
            ]], 200),
        ]);
    }

    /** PayMongo fails once (so the first attempt is refused), then recovers with $sessionId. */
    private function fakeCheckoutDownThenUp(int $status, string $sessionId): void
    {
        Http::fake([
            'api.paymongo.com/v1/checkout_sessions' => Http::sequence()
                ->push(['errors' => [['detail' => 'boom']]], $status)
                ->push(['data' => [
                    'id'         => $sessionId,
                    'attributes' => ['checkout_url' => 'https://checkout.paymongo.test/' . $sessionId],
                ]], 200),
        ]);
    }

    /** A signed-agreement reservation, i.e. one the controller will take an initial payment for. */
    private function signedTenancy(array $reservation = [], array $unit = []): array
    {
        $t = $this->makeOnlineTenancy(array_merge(['rental_status' => 'Rental Agreement Signed'], $reservation));

        if ($unit !== []) {
            $t['reservation']->unit->update($unit);
            $t['reservation']->refresh();
        }

        return $t;
    }

    /** POST to the initial ('initial') or rent ('rent') checkout on one surface, as $user (null = guest). */
    private function startCheckout(string $surface, string $flow, ?User $user, Reservation $reservation, array $data = [])
    {
        $id = $reservation->reservation_id;

        if ($surface === 'api') {
            if ($user) {
                Sanctum::actingAs($user, ['*']);
            }

            return $this->postJson($flow === 'initial'
                ? "/api/v1/tenant/reservations/{$id}/pay"
                : "/api/v1/tenant/tenancy/{$id}/pay-rent", $data);
        }

        if ($user) {
            $this->actingAs($user);
        }

        return $this->post(route($flow === 'initial' ? 'payments.checkout' : 'payments.rentCheckout', $reservation), $data);
    }

    private function assertRefusedWithPaymentError($response, string $surface): void
    {
        if ($surface === 'api') {
            $response->assertStatus(422)->assertJsonValidationErrors('payment');
        } else {
            $response->assertSessionHasErrors('payment');
        }
    }

    private function assertCheckoutUrlReturned($response, string $surface, string $url): void
    {
        if ($surface === 'api') {
            $response->assertOk()->assertJsonPath('data.checkout_url', $url);
        } else {
            $response->assertRedirect($url);
        }
    }

    /** The one checkout-session POST PayMongo received, as its `data.attributes`. */
    private function sentCheckoutAttributes(): array
    {
        $posts = Http::recorded(fn (Request $request) => $request->method() === 'POST')->values();

        $this->assertCount(1, $posts, 'Exactly one checkout session should have been requested.');

        /** @var Request $request */
        $request = $posts[0][0];
        $this->assertSame(self::CHECKOUT_ENDPOINT, $request->url());
        $this->assertSame(
            ['Basic ' . base64_encode('sk_test_x:')],
            $request->header('Authorization'),
            'PayMongo is authenticated with the secret key as the basic-auth user.'
        );

        return $request->data()['data']['attributes'];
    }

    private function paymentsOf(Reservation $reservation)
    {
        return Payment::where("reservation_id", $reservation->reservation_id)->orderBy("payment_id")->get();
    }

    // ─── (1) authorization: initial checkout ────────────────────

    #[DataProvider('surfaces')]
    public function test_another_tenant_cannot_start_the_initial_checkout_for_someone_elses_reservation(string $surface): void
    {
        Http::fake();
        $t = $this->signedTenancy();

        $this->startCheckout($surface, 'initial', $this->makeTenant(), $t['reservation'])->assertForbidden();

        Http::assertNothingSent();
        $this->assertCount(0, $this->paymentsOf($t['reservation']), 'A refused request must not leave a Payment row.');
    }

    #[DataProvider('surfaces')]
    public function test_the_landlord_cannot_start_an_initial_checkout_even_on_their_own_property(string $surface): void
    {
        Http::fake();
        $t = $this->signedTenancy();

        $this->startCheckout($surface, 'initial', $t['landlord'], $t['reservation'])->assertForbidden();

        Http::assertNothingSent();
        $this->assertCount(0, $this->paymentsOf($t['reservation']));
    }

    #[DataProvider('surfaces')]
    public function test_a_guest_cannot_start_an_initial_checkout(string $surface): void
    {
        Http::fake();
        $t = $this->signedTenancy();

        $response = $this->startCheckout($surface, 'initial', null, $t['reservation']);

        $surface === 'api' ? $response->assertUnauthorized() : $response->assertRedirect(route('login'));
        Http::assertNothingSent();
        $this->assertCount(0, $this->paymentsOf($t['reservation']));
    }

    // ─── (1) authorization: rent checkout ───────────────────────

    #[DataProvider('surfaces')]
    public function test_another_tenant_cannot_start_a_rent_checkout_for_someone_elses_tenancy(string $surface): void
    {
        Http::fake();
        $t = $this->makeOnlineTenancy();

        $this->startCheckout($surface, 'rent', $this->makeTenant(), $t['reservation'])->assertForbidden();

        Http::assertNothingSent();
        $this->assertCount(0, $this->paymentsOf($t['reservation']));
    }

    #[DataProvider('surfaces')]
    public function test_the_landlord_cannot_start_a_rent_checkout(string $surface): void
    {
        Http::fake();
        $t = $this->makeOnlineTenancy();

        $this->startCheckout($surface, 'rent', $t['landlord'], $t['reservation'])->assertForbidden();

        Http::assertNothingSent();
        $this->assertCount(0, $this->paymentsOf($t['reservation']));
    }

    #[DataProvider('surfaces')]
    public function test_a_guest_cannot_start_a_rent_checkout(string $surface): void
    {
        Http::fake();
        $t = $this->makeOnlineTenancy();

        $response = $this->startCheckout($surface, 'rent', null, $t['reservation']);

        $surface === 'api' ? $response->assertUnauthorized() : $response->assertRedirect(route('login'));
        Http::assertNothingSent();
        $this->assertCount(0, $this->paymentsOf($t['reservation']));
    }

    // ─── (2) state guards: initial checkout ─────────────────────

    #[DataProvider('surfaces')]
    public function test_the_initial_checkout_is_refused_unless_the_agreement_is_signed(string $surface): void
    {
        Http::fake();

        foreach (['Inquiry', 'Under Negotiation', 'Pending Rental Agreement', 'Occupied', 'Completed', 'Cancelled'] as $status) {
            $t = $this->makeOnlineTenancy(['rental_status' => $status]);

            $response = $this->startCheckout($surface, 'initial', $t['tenant'], $t['reservation']);

            $this->assertRefusedWithPaymentError($response, $surface);
            $this->assertCount(0, $this->paymentsOf($t['reservation']), "A {$status} reservation must not get a Payment row.");
        }

        Http::assertNothingSent();
    }

    #[DataProvider('surfaces')]
    public function test_a_second_initial_checkout_is_refused_while_one_is_still_pending(string $surface): void
    {
        Http::fake();
        $t = $this->signedTenancy();
        $first = $this->makePendingCheckout($t['reservation'], 'cs_checkout_first');

        $response = $this->startCheckout($surface, 'initial', $t['tenant'], $t['reservation']);

        $this->assertRefusedWithPaymentError($response, $surface);
        Http::assertNothingSent();
        $payments = $this->paymentsOf($t['reservation']);
        $this->assertCount(1, $payments, 'The double-click must not add a second Pending row.');
        $this->assertSame($first->payment_id, $payments->first()->payment_id);
        $this->assertSame('cs_checkout_first', $payments->first()->paymongo_checkout_session_id);
    }

    #[DataProvider('surfaces')]
    public function test_a_second_initial_checkout_is_refused_once_the_first_is_already_paid_into_escrow(string $surface): void
    {
        Http::fake();
        $t = $this->signedTenancy();
        $this->makePayment($t['reservation'], [
            'payment_type'                 => 'Initial',
            'billing_period'               => null,
            'payment_method'               => 'GCash',
            'status'                       => 'Held',
            'paymongo_checkout_session_id' => 'cs_checkout_held',
        ]);

        $response = $this->startCheckout($surface, 'initial', $t['tenant'], $t['reservation']);

        $this->assertRefusedWithPaymentError($response, $surface);
        Http::assertNothingSent();
        $this->assertCount(1, $this->paymentsOf($t['reservation']));
    }

    // ─── (2) state guards: rent checkout ────────────────────────

    #[DataProvider('surfaces')]
    public function test_rent_cannot_be_paid_unless_the_tenancy_is_occupied(string $surface): void
    {
        Http::fake();

        foreach (['Rental Agreement Signed', 'Reserved', 'Completed', 'Cancelled'] as $status) {
            $t = $this->makeOnlineTenancy(['rental_status' => $status]);

            $this->startCheckout($surface, 'rent', $t['tenant'], $t['reservation'])->assertForbidden();

            $this->assertCount(0, $this->paymentsOf($t['reservation']), "A {$status} tenancy must not get a rent Payment row.");
        }

        Http::assertNothingSent();
    }

    #[DataProvider('surfaces')]
    public function test_nothing_is_charged_when_every_billed_month_is_already_paid(string $surface): void
    {
        Http::fake();
        $t = $this->makeOnlineTenancy();
        foreach (['2026-08-01', '2026-09-01', '2026-10-01'] as $month) {
            $this->makeRecordedPayment($t['reservation'], $t['landlord'], ['billing_period' => $month]);
        }

        $response = $this->startCheckout($surface, 'rent', $t['tenant'], $t['reservation']);

        $surface === 'api'
            ? $response->assertOk()->assertJsonPath('message', 'Your rent is already fully paid.')
            : $response->assertSessionHas('success', 'Your rent is already fully paid.');
        Http::assertNothingSent();
        $this->assertCount(3, $this->paymentsOf($t['reservation']), 'No Pending row may be created when nothing is owed.');
    }

    #[DataProvider('surfaces')]
    public function test_a_second_rent_checkout_for_the_same_month_is_refused_while_one_is_pending(string $surface): void
    {
        Http::fake();
        $t = $this->makeOnlineTenancy();
        $this->makePendingCheckout($t['reservation'], 'cs_checkout_rent_first', [
            'payment_type'   => 'Monthly',
            'billing_period' => '2026-08-01',
            'amount'         => 5000,
        ]);

        $response = $this->startCheckout($surface, 'rent', $t['tenant'], $t['reservation']);

        $this->assertRefusedWithPaymentError($response, $surface);
        Http::assertNothingSent();
        $this->assertCount(1, $this->paymentsOf($t['reservation']));
    }

    // ─── (3) amount correctness: initial checkout ───────────────

    #[DataProvider('surfaces')]
    public function test_the_initial_checkout_asks_paymongo_for_the_agreed_rent_in_centavos(string $surface): void
    {
        $this->fakeCheckoutCreated('cs_checkout_initial');
        // The rent charged is the one agreed in the lease (agreed_monthly_rent), not the unit's live price.
        $t = $this->signedTenancy(['agreed_monthly_rent' => 7500.50], ['security_deposit' => 9000]);
        $reservation = $t['reservation'];

        // A client that tries to name its own price must be ignored.
        $response = $this->startCheckout($surface, 'initial', $t['tenant'], $reservation, ['amount' => 1, 'amount_centavos' => 100]);

        $this->assertCheckoutUrlReturned($response, $surface, 'https://checkout.paymongo.test/cs_checkout_initial');

        $attributes = $this->sentCheckoutAttributes();
        $this->assertCount(2, $attributes['line_items'], 'Rent, then the security deposit; no fee is set.');
        $this->assertSame(750050, $attributes['line_items'][0]['amount'], 'PHP 7,500.50 is 750,050 centavos.');
        $this->assertSame('PHP', $attributes['line_items'][0]['currency']);
        $this->assertSame(1, $attributes['line_items'][0]['quantity']);
        $this->assertSame(900000, $attributes['line_items'][1]['amount'], 'The PHP 9,000 deposit rides on the same checkout.');
        $this->assertStringContainsString('deposit', $attributes['line_items'][1]['name']);
        $this->assertSame(['gcash', 'qrph'], $attributes['payment_method_types']);
        $this->assertSame(route('payments.success', $reservation), $attributes['success_url']);
        $this->assertSame(route('agreements.show', $reservation), $attributes['cancel_url']);

        $payments = $this->paymentsOf($reservation);
        $this->assertCount(2, $payments, 'One row for the rent, one for the deposit.');
        $this->assertSame(['Initial', 'Deposit'], $payments->pluck('payment_type')->all());
        $this->assertSame([7500.50, 9000.0], $payments->map(fn ($p) => (float) $p->amount)->all());
        $this->assertSame(['cs_checkout_initial'], $payments->pluck('paymongo_checkout_session_id')->unique()->values()->all(), 'Both rows share the one PayMongo session.');
        $payment = $payments->first();
        $this->assertSame('Initial', $payment->payment_type);
        $this->assertNull($payment->billing_period);
        $this->assertSame('7500.50', (string) $payment->amount);
        $this->assertSame('Pending', $payment->status);
        $this->assertSame('GCash', $payment->payment_method);
        $this->assertNull($payment->paid_at, 'Starting a checkout must not mark anything paid.');
        $this->assertSame('Pending', $payments->last()->status);
        $this->assertSame('0.00', (string) $payments->last()->service_fee, 'The fee, when there is one, sits on the rent row only.');
    }

    #[DataProvider('surfaces')]
    public function test_a_unit_price_edited_after_signing_does_not_change_what_the_tenant_is_charged(string $surface): void
    {
        $this->fakeCheckoutCreated('cs_checkout_frozen');
        // Agreed rent is the fixture's 5,000; the landlord then raises the unit to 9,999.
        $t = $this->signedTenancy(unit: ['rental_fee' => 9999]);

        $this->startCheckout($surface, 'initial', $t['tenant'], $t['reservation']);

        $this->assertSame(500000, $this->sentCheckoutAttributes()['line_items'][0]['amount'], 'The tenant pays what they signed, PHP 5,000.');
        $this->assertSame('5000.00', (string) $this->paymentsOf($t['reservation'])->first()->amount);
    }

    #[DataProvider('surfaces')]
    public function test_an_initial_amount_with_centavos_reaches_paymongo_without_losing_one(string $surface): void
    {
        $this->fakeCheckoutCreated('cs_checkout_centavo');
        $t = $this->signedTenancy(['agreed_monthly_rent' => 512.05]);

        $this->startCheckout($surface, 'initial', $t['tenant'], $t['reservation']);

        $this->assertSame(51205, $this->sentCheckoutAttributes()['line_items'][0]['amount']);
        $this->assertSame('512.05', (string) $this->paymentsOf($t['reservation'])->first()->amount);
    }

    // ─── (3) amount correctness: rent checkout ──────────────────

    #[DataProvider('surfaces')]
    public function test_the_rent_checkout_charges_the_oldest_unpaid_month_for_its_remaining_balance(string $surface): void
    {
        $this->fakeCheckoutCreated('cs_checkout_rent');
        $t = $this->makeOnlineTenancy();
        $reservation = $t['reservation'];
        // August is part-paid offline: PHP 2,000 of 5,000, so PHP 3,000 is still owed for it.
        $this->makeRecordedPayment($reservation, $t['landlord'], ['billing_period' => '2026-08-01', 'amount' => 2000]);

        // The tenant tries to pick the month and the price; both are the server's call.
        $response = $this->startCheckout($surface, 'rent', $t['tenant'], $reservation, [
            'amount'         => 1,
            'billing_period' => '2026-10-01',
        ]);

        $this->assertCheckoutUrlReturned($response, $surface, 'https://checkout.paymongo.test/cs_checkout_rent');

        $attributes = $this->sentCheckoutAttributes();
        $this->assertSame(300000, $attributes['line_items'][0]['amount'], 'PHP 3,000 balance is 300,000 centavos.');
        $this->assertStringContainsString('Aug 2026', $attributes['line_items'][0]['name']);
        $this->assertSame(route('payments.rent.success', $reservation), $attributes['success_url']);
        $this->assertSame(route('tenancy.show', $reservation), $attributes['cancel_url']);

        $pending = $this->paymentsOf($reservation)->firstWhere('status', 'Pending');
        $this->assertNotNull($pending, 'A Pending rent row should exist.');
        $this->assertSame('cs_checkout_rent', $pending->paymongo_checkout_session_id);
        $this->assertSame('Monthly', $pending->payment_type);
        $this->assertSame('2026-08-01', $pending->billing_period->toDateString());
        $this->assertSame('3000.00', (string) $pending->amount);
        $this->assertSame('GCash', $pending->payment_method);
        $this->assertNull($pending->paid_at);
        $this->assertCount(2, $this->paymentsOf($reservation), 'Only the offline payment and the one Pending row.');
    }

    #[DataProvider('surfaces')]
    public function test_the_rent_checkout_charges_rent_plus_the_late_fee_the_lease_agreed(string $surface): void
    {
        $this->fakeCheckoutCreated('cs_checkout_fee');
        $t = $this->makeOnlineTenancy();
        $reservation = $t['reservation'];
        $reservation->forceFill(['lease_snapshot' => ['grace_days' => 0, 'late_fee' => ['type' => 'fixed', 'value' => 100]]])->save();
        $this->makeRecordedPayment($reservation, $t['landlord'], ['billing_period' => '2026-08-01', 'amount' => 5000, 'paid_at' => '2026-08-04 10:00:00']);

        $this->startCheckout($surface, 'rent', $t['tenant'], $reservation);

        $attributes = $this->sentCheckoutAttributes();
        $this->assertSame(510000, $attributes['line_items'][0]['amount'], 'September: PHP 5,000 rent + PHP 100 late fee.');
        $this->assertStringContainsString('late fee', $attributes['line_items'][0]['name']);
        $this->assertSame('5100.00', (string) $this->paymentsOf($reservation)->firstWhere('status', 'Pending')->amount);
    }

    #[DataProvider('surfaces')]
    public function test_the_rent_checkout_moves_on_to_the_next_month_once_the_oldest_is_settled(string $surface): void
    {
        $this->fakeCheckoutCreated('cs_checkout_sep');
        $t = $this->makeOnlineTenancy();
        $this->makeRecordedPayment($t['reservation'], $t['landlord'], ['billing_period' => '2026-08-01', 'amount' => 5000]);

        $this->startCheckout($surface, 'rent', $t['tenant'], $t['reservation']);

        $this->assertSame(500000, $this->sentCheckoutAttributes()['line_items'][0]['amount']);
        $pending = $this->paymentsOf($t['reservation'])->firstWhere('status', 'Pending');
        $this->assertSame('2026-09-01', $pending->billing_period->toDateString());
        $this->assertSame('5000.00', (string) $pending->amount);
    }

    #[DataProvider('surfaces')]
    public function test_the_rent_checkout_rounds_a_centavo_balance_to_the_exact_centavo(string $surface): void
    {
        $this->fakeCheckoutCreated('cs_checkout_centavo_rent');
        $t = $this->makeOnlineTenancy(['agreed_monthly_rent' => 512.05]);

        $this->startCheckout($surface, 'rent', $t['tenant'], $t['reservation']);

        $this->assertSame(51205, $this->sentCheckoutAttributes()['line_items'][0]['amount']);
        $this->assertSame('512.05', (string) $this->paymentsOf($t['reservation'])->firstWhere('status', 'Pending')->amount);
    }

    // ─── (5) PayMongo failure at checkout creation ──────────────

    #[DataProvider('surfaces')]
    public function test_a_paymongo_failure_on_the_initial_checkout_leaves_no_orphan_pending_row_and_can_be_retried(string $surface): void
    {
        $this->fakeCheckoutDownThenUp(500, 'cs_checkout_retry');
        $t = $this->signedTenancy();

        $response = $this->startCheckout($surface, 'initial', $t['tenant'], $t['reservation']);

        $this->assertRefusedWithPaymentError($response, $surface);
        $this->assertCount(0, $this->paymentsOf($t['reservation']), 'The placeholder row must be removed, or the next click is told a session is in progress.');

        // PayMongo has recovered: the very next attempt must go through.
        $retry = $this->startCheckout($surface, 'initial', $t['tenant'], $t['reservation']);

        $this->assertCheckoutUrlReturned($retry, $surface, 'https://checkout.paymongo.test/cs_checkout_retry');
        $payments = $this->paymentsOf($t['reservation']);
        $this->assertCount(2, $payments, 'The failed attempt left nothing behind: only the retry\'s rent and deposit rows.');
        $this->assertSame(['cs_checkout_retry'], $payments->pluck('paymongo_checkout_session_id')->unique()->values()->all());
    }

    #[DataProvider('surfaces')]
    public function test_a_paymongo_failure_on_the_rent_checkout_leaves_no_orphan_pending_row_and_can_be_retried(string $surface): void
    {
        $this->fakeCheckoutDownThenUp(401, 'cs_checkout_rent_retry');
        $t = $this->makeOnlineTenancy();

        $response = $this->startCheckout($surface, 'rent', $t['tenant'], $t['reservation']);

        $this->assertRefusedWithPaymentError($response, $surface);
        $this->assertCount(0, $this->paymentsOf($t['reservation']));

        $retry = $this->startCheckout($surface, 'rent', $t['tenant'], $t['reservation']);

        $this->assertCheckoutUrlReturned($retry, $surface, 'https://checkout.paymongo.test/cs_checkout_rent_retry');
        $pending = $this->paymentsOf($t['reservation'])->firstWhere('status', 'Pending');
        $this->assertSame('cs_checkout_rent_retry', $pending->paymongo_checkout_session_id);
        $this->assertSame('2026-08-01', $pending->billing_period->toDateString());
    }

    // ─── online is offered per landlord, and never beside an "I paid" report ───

    /** The refusal when online is not offered here: a 409 on the API, the plain message on the web. */
    private function assertOnlineNotOffered($response, string $surface): void
    {
        if ($surface === 'api') {
            $response->assertStatus(409);
        } else {
            $response->assertSessionHasErrors(['payment' => Payments::OFFLINE_MESSAGE]);
        }
    }

    /** @return array<string, array{string, string}> */
    public static function surfacesAndFlows(): array
    {
        return [
            'web initial' => ['web', 'initial'], 'web rent' => ['web', 'rent'],
            'api initial' => ['api', 'initial'], 'api rent' => ['api', 'rent'],
        ];
    }

    #[DataProvider('surfacesAndFlows')]
    public function test_a_landlord_without_gcash_details_cannot_be_paid_online(string $surface, string $flow): void
    {
        Http::fake();
        $t = $this->makeTenancy(['rental_status' => $flow === 'initial' ? 'Rental Agreement Signed' : 'Occupied']);

        $this->assertFalse($t['landlord']->acceptsOnlinePayments());
        $this->assertOnlineNotOffered($this->startCheckout($surface, $flow, $t['tenant'], $t['reservation']), $surface);

        Http::assertNothingSent();
        $this->assertCount(0, $this->paymentsOf($t['reservation']));
    }

    #[DataProvider('surfacesAndFlows')]
    public function test_a_landlord_who_opted_out_cannot_be_paid_online(string $surface, string $flow): void
    {
        Http::fake();
        $t = $this->makeOnlineTenancy(['rental_status' => $flow === 'initial' ? 'Rental Agreement Signed' : 'Occupied']);
        $t['landlord']->setAcceptsOnlinePayments(false);

        $this->assertOnlineNotOffered($this->startCheckout($surface, $flow, $t['tenant'], $t['reservation']), $surface);

        Http::assertNothingSent();
        $this->assertCount(0, $this->paymentsOf($t['reservation']));
    }

    #[DataProvider('surfacesAndFlows')]
    public function test_a_checkout_is_refused_while_an_i_paid_report_is_waiting(string $surface, string $flow): void
    {
        Http::fake();
        $t = $flow === 'initial' ? $this->signedTenancy() : $this->makeOnlineTenancy();
        PaymentClaims::file($t['reservation'], $t['tenant'], $flow === 'initial'
            ? ['claim_type' => 'MoveIn', 'amount' => 10000, 'payment_method' => 'Maya', 'reference_no' => 'MY1', 'paid_on' => '2026-10-13']
            : ['claim_type' => 'Monthly', 'billing_period' => '2026-08-01', 'amount' => 5000, 'payment_method' => 'Maya', 'reference_no' => 'MY1', 'paid_on' => '2026-10-13']);

        $this->assertRefusedWithPaymentError($this->startCheckout($surface, $flow, $t['tenant'], $t['reservation']), $surface);

        Http::assertNothingSent();
        $this->assertCount(0, $this->paymentsOf($t['reservation']), 'No placeholder row may be left behind.');
    }

    #[DataProvider('surfaces')]
    public function test_a_report_for_another_month_does_not_block_this_months_checkout(string $surface): void
    {
        $this->fakeCheckoutCreated('cs_other_month');
        $t = $this->makeOnlineTenancy();
        PaymentClaims::file($t['reservation'], $t['tenant'], [
            'claim_type' => 'Monthly', 'billing_period' => '2026-09-01', 'amount' => 5000,
            'payment_method' => 'Maya', 'reference_no' => 'MY2', 'paid_on' => '2026-10-13',
        ]);

        // Aug is the earliest unpaid month, which is what online pays; the waiting report is for Sep.
        $this->assertCheckoutUrlReturned($this->startCheckout($surface, 'rent', $t['tenant'], $t['reservation']), $surface, 'https://checkout.paymongo.test/cs_other_month');
    }

    // ─── the online payment fee ─────────────────────────────────

    #[DataProvider('surfaces')]
    public function test_a_move_in_with_a_fee_charges_rent_deposit_and_fee_as_three_lines_on_one_session(string $surface): void
    {
        config(['rentals.online_payment_fee_percent' => 2, 'rentals.online_payment_fee_fixed' => 10]);
        $this->fakeCheckoutCreated('cs_fee_movein');
        $t = $this->signedTenancy(['agreed_monthly_rent' => 5000], ['security_deposit' => 3000]);

        $this->startCheckout($surface, 'initial', $t['tenant'], $t['reservation']);

        $lines = $this->sentCheckoutAttributes()['line_items'];
        $this->assertSame([500000, 300000, 11000], array_column($lines, 'amount'), 'Rent, deposit, then the PHP 110 fee (2% of the rent + 10).');
        $this->assertSame('Online payment fee', $lines[2]['name']);

        [$rent, $deposit] = $this->paymentsOf($t['reservation'])->all();
        $this->assertSame('110.00', (string) $rent->service_fee, 'The fee sits on the rent row...');
        $this->assertSame('0.00', (string) $deposit->service_fee, '...and never on the deposit row.');
        $this->assertSame('5000.00', (string) $rent->amount, 'amount stays what the landlord is owed; the fee is not part of it.');
    }

    #[DataProvider('surfaces')]
    public function test_a_rent_checkout_with_a_fee_adds_one_fee_line_and_no_deposit(string $surface): void
    {
        config(['rentals.online_payment_fee_percent' => 2, 'rentals.online_payment_fee_fixed' => 10]);
        $this->fakeCheckoutCreated('cs_fee_rent');
        $t = $this->makeOnlineTenancy();

        $this->startCheckout($surface, 'rent', $t['tenant'], $t['reservation']);

        $this->assertSame([500000, 11000], array_column($this->sentCheckoutAttributes()['line_items'], 'amount'));
        $payments = $this->paymentsOf($t['reservation']);
        $this->assertCount(1, $payments);
        $this->assertSame('110.00', (string) $payments->first()->service_fee);
    }

    #[DataProvider('surfacesAndFlows')]
    public function test_no_fee_rate_means_no_fee_line_and_a_zero_fee(string $surface, string $flow): void
    {
        $this->fakeCheckoutCreated('cs_nofee');
        $t = $flow === 'initial' ? $this->signedTenancy([], ['security_deposit' => 0]) : $this->makeOnlineTenancy();

        $this->startCheckout($surface, $flow, $t['tenant'], $t['reservation']);

        $this->assertCount(1, $this->sentCheckoutAttributes()['line_items'], 'Just the rent: no deposit on this unit, no fee set.');
        $this->assertSame('0.00', (string) $this->paymentsOf($t['reservation'])->first()->service_fee);
    }

    public function test_the_fee_is_frozen_when_the_checkout_starts(): void
    {
        config(['rentals.online_payment_fee_percent' => 2, 'rentals.online_payment_fee_fixed' => 10]);
        $this->fakeCheckoutCreated('cs_frozen_fee');
        $t = $this->makeOnlineTenancy();
        $this->startCheckout('web', 'rent', $t['tenant'], $t['reservation']);

        config(['rentals.online_payment_fee_percent' => 9, 'rentals.online_payment_fee_fixed' => 99]);

        $this->assertSame('110.00', (string) $this->paymentsOf($t['reservation'])->first()->fresh()->service_fee);
    }

    // ─── why PayMongo said no ───────────────────────────────────

    /** @return array<string, array{int, string}> */
    public static function refusals(): array
    {
        return [
            'key rejected' => [401, 'not working right now'],
            'forbidden' => [403, 'not working right now'],
            'busy' => [429, 'busy right now'],
            'down' => [503, 'busy right now'],
            'anything else' => [422, 'Could not create payment session'],
        ];
    }

    #[DataProvider('refusals')]
    public function test_the_tenant_is_told_why_the_checkout_could_not_open(int $status, string $expected): void
    {
        Http::fake(['api.paymongo.com/v1/checkout_sessions' => Http::response(['errors' => [['detail' => 'nope']]], $status)]);
        $t = $this->signedTenancy();

        $response = $this->startCheckout('web', 'initial', $t['tenant'], $t['reservation']);

        $response->assertSessionHasErrors('payment');
        $this->assertStringContainsString($expected, session('errors')->first('payment'));
        $this->assertCount(0, $this->paymentsOf($t['reservation']), 'No dead placeholder is left behind.');
    }

    public function test_a_rejected_key_alerts_the_admins_once_and_not_for_a_busy_service(): void
    {
        Http::fake(['api.paymongo.com/v1/checkout_sessions' => Http::response([], 401)]);
        $admin = $this->makeUserWithRole('Admin');
        $t = $this->signedTenancy();

        $this->startCheckout('web', 'initial', $t['tenant'], $t['reservation']);
        $this->startCheckout('web', 'initial', $t['tenant'], $t['reservation']);

        $this->assertSame(1, \App\Models\Notification::where('user_id', $admin->user_id)->where('title', 'Online checkout is failing')->count());
    }
}
