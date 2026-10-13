<?php

namespace Tests\Feature\Payments;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Notification;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\User;
use App\Services\RentLedger;
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
 * The poll fallback that SETTLES a tenant's online payment when the user lands
 * back from PayMongo: web `payments.success` / `payments.rent.success` and the
 * mobile `payment/reconcile` endpoints, all sharing ReconcilesPaymongoCheckout.
 *
 * PayMongo cannot reach localhost, so in local dev this is the only path that
 * ever moves a payment off Pending. Pinned here:
 *   - only the reservation's own tenant can trigger it, and a refused request
 *     never asks PayMongo anything;
 *   - "paid" settles Initial -> Held (escrow, both parties notified) and
 *     Monthly -> Paid + Pending Payout, once;
 *   - "not paid", an unreadable answer or a PayMongo error changes nothing and
 *     tells nobody;
 *   - a payment that has already moved on is never touched again, so a stale
 *     "paid" answer cannot drag a Released payout back to Held.
 *
 * Every case runs on all four entry points (initial/rent x web/api).
 */
class TenantReconcileTest extends TestCase
{
    use CreatesMarketplaceFixtures, CreatesPaymentFixtures, DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.paymongo.secret_key' => 'sk_test_x']);
        Carbon::setTestNow('2026-10-14 10:00:00');
    }

    /** @return array<string, array{string, string}> flow ('initial'|'monthly'), surface ('web'|'api') */
    public static function entryPoints(): array
    {
        return [
            'initial via web' => ['initial', 'web'],
            'initial via api' => ['initial', 'api'],
            'monthly via web' => ['monthly', 'web'],
            'monthly via api' => ['monthly', 'api'],
        ];
    }

    // ─── helpers ────────────────────────────────────────────────

    /**
     * A tenancy with a conversation (so system messages land somewhere) and one
     * payment of the flow's type, Pending on $sessionId unless $attributes say otherwise.
     *
     * @return array{landlord: User, tenant: User, reservation: Reservation, payment: Payment}
     */
    private function tenancyWithCheckout(string $flow, string $sessionId, array $attributes = []): array
    {
        $t = $flow === 'initial'
            ? $this->makeTenancy(['rental_status' => 'Rental Agreement Signed'])
            : $this->makeTenancy();

        $conversation = Conversation::create([
            'tenant_id'   => $t['tenant']->user_id,
            'landlord_id' => $t['landlord']->user_id,
            'property_id' => $t['reservation']->property_id,
        ]);
        $t['reservation']->update(['conversation_id' => $conversation->conversation_id]);

        $defaults = $flow === 'monthly'
            ? ['payment_type' => 'Monthly', 'billing_period' => '2026-08-01', 'payment_method' => 'GCash']
            : [];

        $t['payment'] = $this->makePendingCheckout($t['reservation'], $sessionId, array_merge($defaults, $attributes));

        return $t;
    }

    private function reconcile(string $flow, string $surface, ?User $user, Reservation $reservation)
    {
        $id = $reservation->reservation_id;

        if ($surface === 'api') {
            if ($user) {
                Sanctum::actingAs($user, ['*']);
            }

            return $this->postJson($flow === 'initial'
                ? "/api/v1/tenant/reservations/{$id}/payment/reconcile"
                : "/api/v1/tenant/tenancy/{$id}/payment/reconcile");
        }

        if ($user) {
            $this->actingAs($user);
        }

        return $this->get(route($flow === 'initial' ? 'payments.success' : 'payments.rent.success', $reservation));
    }

    private function fakeSession(string $sessionId, array $body, int $status = 200): void
    {
        Http::fake(["api.paymongo.com/v1/checkout_sessions/{$sessionId}" => Http::response($body, $status)]);
    }

    private function paidSession(string $sessionId, string $method = 'gcash'): array
    {
        return ['data' => [
            'id'         => $sessionId,
            'attributes' => [
                'status'              => 'paid',
                'payment_intent'      => ['id' => 'pi_' . $sessionId],
                'payments'            => [['id' => 'pay_' . $sessionId, 'attributes' => ['source' => ['type' => $method]]]],
                'payment_method_used' => $method,
            ],
        ]];
    }

    private function unpaidSession(string $sessionId): array
    {
        return ['data' => ['id' => $sessionId, 'attributes' => ['status' => 'active', 'payments' => []]]];
    }

    /** How many GET status polls PayMongo received. */
    private function pollsSent(): int
    {
        return Http::recorded(fn (Request $request) => $request->method() === 'GET')->count();
    }

    /** Notifications and chat messages the two parties would have seen. */
    private function noise(array $t): array
    {
        return [
            'notifications' => Notification::whereIn('user_id', [$t['tenant']->user_id, $t['landlord']->user_id])->count(),
            'messages'      => Message::where('conversation_id', $t['reservation']->conversation_id)->count(),
        ];
    }

    private function assertNothingSettled(array $t, array $before, string $message = ''): void
    {
        $payment = $t['payment']->fresh();

        $this->assertSame('Pending', $payment->status, $message ?: 'The payment must stay Pending.');
        $this->assertNull($payment->paid_at);
        $this->assertNull($payment->payout_status);
        $this->assertNull($payment->paymongo_payment_id);
        $this->assertSame($before, $this->noise($t), 'Nobody may be notified of a payment that did not settle.');
    }

    // ─── (1) authorization ──────────────────────────────────────

    #[DataProvider('entryPoints')]
    public function test_another_tenant_cannot_reconcile_someone_elses_payment(string $flow, string $surface): void
    {
        Http::fake();
        $t = $this->tenancyWithCheckout($flow, 'cs_checkout_other_tenant');
        $before = $this->noise($t);

        $this->reconcile($flow, $surface, $this->makeTenant(), $t['reservation'])->assertForbidden();

        Http::assertNothingSent();
        $this->assertNothingSettled($t, $before);
    }

    #[DataProvider('entryPoints')]
    public function test_the_landlord_cannot_reconcile_a_tenants_payment(string $flow, string $surface): void
    {
        Http::fake();
        $t = $this->tenancyWithCheckout($flow, 'cs_checkout_landlord');
        $before = $this->noise($t);

        $this->reconcile($flow, $surface, $t['landlord'], $t['reservation'])->assertForbidden();

        Http::assertNothingSent();
        $this->assertNothingSettled($t, $before);
    }

    #[DataProvider('entryPoints')]
    public function test_a_guest_cannot_reconcile_a_payment(string $flow, string $surface): void
    {
        Http::fake();
        $t = $this->tenancyWithCheckout($flow, 'cs_checkout_guest');
        $before = $this->noise($t);

        $response = $this->reconcile($flow, $surface, null, $t['reservation']);

        $surface === 'api' ? $response->assertUnauthorized() : $response->assertRedirect(route('login'));
        Http::assertNothingSent();
        $this->assertNothingSettled($t, $before);
    }

    public function test_rent_reconcile_is_refused_once_the_tenancy_is_no_longer_occupied(): void
    {
        Http::fake();
        $t = $this->tenancyWithCheckout('monthly', 'cs_checkout_ended');
        $t['reservation']->update(['rental_status' => 'Completed']);
        $before = $this->noise($t);

        $this->reconcile('monthly', 'api', $t['tenant'], $t['reservation'])->assertForbidden();

        Http::assertNothingSent();
        $this->assertNothingSettled($t, $before);
    }

    // ─── (4) PayMongo says paid ─────────────────────────────────

    #[DataProvider('entryPoints')]
    public function test_a_paid_session_settles_the_payment_the_way_its_type_requires(string $flow, string $surface): void
    {
        $this->fakeSession('cs_checkout_paid', $this->paidSession('cs_checkout_paid'));
        $t = $this->tenancyWithCheckout($flow, 'cs_checkout_paid');

        $response = $this->reconcile($flow, $surface, $t['tenant'], $t['reservation']);

        $response->assertOk();
        $payment = $t['payment']->fresh();
        $this->assertSame('GCash', $payment->payment_method);
        $this->assertSame('pi_cs_checkout_paid', $payment->paymongo_payment_intent_id);
        $this->assertSame('pay_cs_checkout_paid', $payment->paymongo_payment_id);
        $this->assertNotNull($payment->paid_at);
        $this->assertSame('cs_checkout_paid', $payment->paymongo_checkout_session_id, 'The session id stays on the row.');

        if ($flow === 'initial') {
            $this->assertSame('Held', $payment->status, 'Initial money goes into escrow, not straight to the landlord.');
            $this->assertNull($payment->payout_status, 'Held money is not payable to the landlord yet.');
            $this->assertNull($payment->released_at);
        } else {
            $this->assertSame('Paid', $payment->status);
            $this->assertSame('Pending Payout', $payment->payout_status, 'Online rent is owed to the landlord at once.');
            $this->assertSame('2026-08-01', $payment->billing_period->toDateString());
        }

        if ($surface === 'api') {
            $response->assertJsonPath('data.payment_id', $payment->payment_id)
                ->assertJsonPath('data.status', $payment->status);
        }

        $poll = Http::recorded()->first()[0];
        $this->assertSame('GET', $poll->method());
        $this->assertSame('https://api.paymongo.com/v1/checkout_sessions/cs_checkout_paid', $poll->url());
        $this->assertSame(['Basic ' . base64_encode('sk_test_x:')], $poll->header('Authorization'));
    }

    #[DataProvider('entryPoints')]
    public function test_settling_tells_the_conversation_and_funding_escrow_notifies_both_parties(string $flow, string $surface): void
    {
        $this->fakeSession('cs_checkout_notify', $this->paidSession('cs_checkout_notify'));
        $t = $this->tenancyWithCheckout($flow, 'cs_checkout_notify');

        $this->reconcile($flow, $surface, $t['tenant'], $t['reservation'])->assertOk();

        $system = Message::where('conversation_id', $t['reservation']->conversation_id)->where('is_system', true)->get();
        $this->assertCount(1, $system);

        if ($flow === 'initial') {
            $this->assertStringContainsString('completed the initial payment', $system->first()->message);
            $this->assertSame(1, Notification::where('user_id', $t['tenant']->user_id)->where('type', 'payment')->count());
            $this->assertSame(1, Notification::where('user_id', $t['landlord']->user_id)->where('type', 'payment')->count());
        } else {
            $this->assertStringContainsString('paid rent for Aug 2026 online', $system->first()->message);
            $this->assertSame(0, Notification::whereIn('user_id', [$t['tenant']->user_id, $t['landlord']->user_id])->where('type', 'payment')->count(),
                'The escrow-funded notification belongs to Pending -> Held only.');
        }
    }

    #[DataProvider('entryPoints')]
    public function test_the_payment_method_paymongo_reports_replaces_the_placeholder_one(string $flow, string $surface): void
    {
        $this->fakeSession('cs_checkout_qr', $this->paidSession('cs_checkout_qr', 'qrph'));
        $t = $this->tenancyWithCheckout($flow, 'cs_checkout_qr');

        $this->reconcile($flow, $surface, $t['tenant'], $t['reservation'])->assertOk();

        $this->assertSame('QRPh', $t['payment']->fresh()->payment_method, 'The placeholder says GCash; the tenant actually paid by QR Ph.');
    }

    public function test_a_settled_rent_payment_closes_that_month_on_the_ledger(): void
    {
        $this->fakeSession('cs_checkout_ledger', $this->paidSession('cs_checkout_ledger'));
        $t = $this->tenancyWithCheckout('monthly', 'cs_checkout_ledger', ['amount' => 5000]);

        $this->reconcile('monthly', 'api', $t['tenant'], $t['reservation'])->assertOk();

        $august = RentLedger::for($t['reservation']->fresh())->periods()->first();
        $this->assertSame('paid', $august['status']);
        $this->assertSame(5000.0, $august['paid']);
    }

    // ─── (4) PayMongo says not paid, or errors ──────────────────

    #[DataProvider('entryPoints')]
    public function test_an_unpaid_session_leaves_the_payment_pending_and_notifies_nobody(string $flow, string $surface): void
    {
        $this->fakeSession('cs_checkout_unpaid', $this->unpaidSession('cs_checkout_unpaid'));
        $t = $this->tenancyWithCheckout($flow, 'cs_checkout_unpaid');
        $before = $this->noise($t);

        $response = $this->reconcile($flow, $surface, $t['tenant'], $t['reservation']);

        $response->assertOk();
        $this->assertSame(1, $this->pollsSent(), 'PayMongo was asked once.');
        $this->assertNothingSettled($t, $before);

        if ($surface === 'api') {
            $response->assertJsonPath('data.status', 'Pending');
        }
    }

    #[DataProvider('entryPoints')]
    public function test_a_paymongo_error_leaves_the_payment_pending_and_notifies_nobody(string $flow, string $surface): void
    {
        foreach ([500, 404, 401, 429] as $status) {
            // A paid-looking body behind an error status must still be ignored.
            $this->fakeSession('cs_checkout_err' . $status, $this->paidSession('cs_checkout_err' . $status), $status);
            $t = $this->tenancyWithCheckout($flow, 'cs_checkout_err' . $status);
            $before = $this->noise($t);

            $this->reconcile($flow, $surface, $t['tenant'], $t['reservation'])->assertOk();

            $this->assertNothingSettled($t, $before, "A PayMongo {$status} must not settle anything.");
        }
    }

    #[DataProvider('entryPoints')]
    public function test_a_pending_payment_without_a_session_id_is_never_polled(string $flow, string $surface): void
    {
        Http::fake();
        $t = $this->tenancyWithCheckout($flow, 'cs_checkout_placeholder', ['paymongo_checkout_session_id' => null]);
        $before = $this->noise($t);

        $this->reconcile($flow, $surface, $t['tenant'], $t['reservation'])->assertOk();

        Http::assertNothingSent();
        $this->assertNothingSettled($t, $before);
    }

    // ─── (4) idempotency and finality ───────────────────────────

    #[DataProvider('entryPoints')]
    public function test_reconciling_twice_settles_once(string $flow, string $surface): void
    {
        $this->fakeSession('cs_checkout_twice', $this->paidSession('cs_checkout_twice'));
        $t = $this->tenancyWithCheckout($flow, 'cs_checkout_twice');

        $this->reconcile($flow, $surface, $t['tenant'], $t['reservation'])->assertOk();
        $afterFirst = $this->noise($t);
        $paidAt = $t['payment']->fresh()->paid_at;
        Carbon::setTestNow('2026-10-14 10:05:00');
        $this->reconcile($flow, $surface, $t['tenant'], $t['reservation'])->assertOk();

        $this->assertSame(1, $this->pollsSent(), 'The second visit must not even ask PayMongo.');
        $this->assertSame($afterFirst, $this->noise($t), 'No second system message or notification.');
        $this->assertEquals($paidAt, $t['payment']->fresh()->paid_at, 'paid_at records the first settlement.');
        $this->assertSame(1, Payment::where('reservation_id', $t['reservation']->reservation_id)->count());
    }

    /** @return array<string, array{string, string, array}> */
    public static function alreadyMovedOn(): array
    {
        return [
            'initial held'        => ['initial', 'Held', []],
            'initial released'    => ['initial', 'Released', ['payout_status' => 'Pending Payout']],
            'initial failed'      => ['initial', 'Failed', []],
            'initial refunded'    => ['initial', 'Refunded', []],
            'monthly paid'        => ['monthly', 'Paid', ['payout_status' => 'Pending Payout']],
            'monthly paid out'    => ['monthly', 'Paid', ['payout_status' => 'Paid Out']],
            'monthly voided'      => ['monthly', 'Voided', []],
        ];
    }

    #[DataProvider('alreadyMovedOn')]
    public function test_a_stale_paid_poll_never_moves_a_payment_that_already_left_pending(string $flow, string $status, array $extra): void
    {
        $this->fakeSession('cs_checkout_stale', $this->paidSession('cs_checkout_stale'));
        $paidAt = Carbon::parse('2026-10-01 09:00:00');
        $t = $this->tenancyWithCheckout($flow, 'cs_checkout_stale', array_merge(
            ['status' => $status, 'paid_at' => $paidAt, 'paymongo_payment_id' => 'pay_original'],
            $extra
        ));
        $before = $this->noise($t);

        foreach (['web', 'api'] as $surface) {
            $this->reconcile($flow, $surface, $t['tenant'], $t['reservation'])->assertOk();
        }

        Http::assertNothingSent();
        $payment = $t['payment']->fresh();
        $this->assertSame($status, $payment->status);
        $this->assertSame($extra['payout_status'] ?? null, $payment->payout_status);
        $this->assertSame('pay_original', $payment->paymongo_payment_id);
        $this->assertEquals($paidAt, $payment->paid_at);
        $this->assertSame($before, $this->noise($t));
    }

    public function test_only_the_newest_payment_of_the_flow_is_polled_not_an_older_pending_one(): void
    {
        $this->fakeSession('cs_checkout_new_one', $this->paidSession('cs_checkout_new_one'));
        $t = $this->tenancyWithCheckout('monthly', 'cs_checkout_old_one');
        $older = $t['payment'];
        $newest = $this->makePendingCheckout($t['reservation'], 'cs_checkout_new_one', [
            'payment_type'   => 'Monthly',
            'billing_period' => '2026-09-01',
        ]);

        $this->reconcile('monthly', 'api', $t['tenant'], $t['reservation'])->assertOk();

        $this->assertSame('Paid', $newest->fresh()->status);
        $this->assertSame('Pending', $older->fresh()->status, 'Only the latest checkout is the one the tenant just returned from.');
        $this->assertSame(1, $this->pollsSent());
    }

    public function test_the_api_returns_null_data_when_the_reservation_has_no_payment_of_that_flow(): void
    {
        Http::fake();
        $t = $this->makeTenancy(['rental_status' => 'Rental Agreement Signed']);

        $this->reconcile('initial', 'api', $t['tenant'], $t['reservation'])
            ->assertOk()
            ->assertJsonPath('data', null);

        Http::assertNothingSent();
    }
}
