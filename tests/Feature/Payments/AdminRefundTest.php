<?php

namespace Tests\Feature\Payments;

use App\Models\AuditLog;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Notification;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\Support\CreatesPaymentFixtures;
use Tests\TestCase;

/**
 * An admin gives a tenant's held online payment back (App\Services\PaymentRefunds, Admin\PaymentRefundController).
 *
 * Pinned here, because each one is real money:
 *   - the whole checkout (rent and deposit) is refunded as one partial refund against its pay_ id, with the
 *     online payment fee added only when the admin ticks it;
 *   - QR Ph goes to its own host and hands the tenant a transfer link to claim;
 *   - only a Held, PayMongo-collected, not-yet-owed group can be refunded, and a refusal from PayMongo changes
 *     nothing;
 *   - a double click refunds once;
 *   - the reservation is cancelled and the unit freed, and both people are told.
 *
 * PayMongo is faked with Http::fake(); nothing here touches the network.
 */
class AdminRefundTest extends TestCase
{
    use CreatesMarketplaceFixtures, CreatesPaymentFixtures, DatabaseTransactions;

    private const REFUNDS = 'https://api.paymongo.com/v1/refunds';

    private const QRPH_REFUNDS = 'https://refunds-api.paymongo.com/v1/refunds';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.paymongo.secret_key' => 'sk_test_x']);
    }

    // ─── helpers ────────────────────────────────────────────────

    private function admin(): User
    {
        return $this->makeUserWithRole('Admin');
    }

    /**
     * A signed agreement whose online move-in is held: a rent row carrying the fee plus a deposit row, both
     * on one checkout session and one PayMongo payment, as Payment::checkoutGroup() describes.
     *
     * @return array{landlord: User, tenant: User, reservation: Reservation, rent: Payment, deposit: Payment}
     */
    private function heldGroup(array $rent = [], array $deposit = []): array
    {
        $t = $this->makeTenancy(['rental_status' => 'Rental Agreement Signed']);

        $conversation = Conversation::create([
            'tenant_id' => $t['tenant']->user_id,
            'landlord_id' => $t['landlord']->user_id,
            'property_id' => $t['reservation']->property_id,
        ]);
        $t['reservation']->update(['conversation_id' => $conversation->conversation_id]);
        $t['reservation']->unit->update(['availability_status' => 'Reserved']);

        $shared = [
            'billing_period' => null,
            'payment_method' => 'GCash',
            'status' => 'Held',
            'paymongo_checkout_session_id' => 'cs_group',
            'paymongo_payment_id' => 'pay_group',
            'paymongo_payment_intent_id' => 'pi_group',
        ];

        $t['rent'] = $this->makePayment($t['reservation'], array_merge($shared, ['payment_type' => 'Initial', 'amount' => 5000, 'service_fee' => 110], $rent));
        $t['deposit'] = $this->makePayment($t['reservation'], array_merge($shared, ['payment_type' => 'Deposit', 'amount' => 1000], $deposit));

        return $t;
    }

    private function fakeRefund(string $status = 'succeeded', array $extra = [], string $host = self::REFUNDS): void
    {
        Http::fake([
            $host => Http::response(['data' => [
                'id' => 'ref_test',
                'type' => 'refund',
                'attributes' => array_merge(['status' => $status, 'amount' => 600000, 'payment_id' => 'pay_group'], $extra),
            ]], 200),
        ]);
    }

    private function refund(User $as, Payment $payment, array $data = [])
    {
        return $this->actingAs($as)->post(route('admin.payments.refund', $payment), array_merge(['refund_reason' => 'The landlord never handed over the keys.'], $data));
    }

    /** The attributes of the one refund request PayMongo received. */
    private function sentRefund(): array
    {
        $sent = Http::recorded(fn (Request $r) => $r->method() === 'POST')->first()[0];

        return $sent->data()['data']['attributes'];
    }

    private function assertNothingChanged(array $t): void
    {
        $this->assertSame('Held', $t['rent']->fresh()->status);
        $this->assertSame('Held', $t['deposit']->fresh()->status);
        $this->assertNull($t['rent']->fresh()->refunded_at);
        $this->assertSame('Rental Agreement Signed', $t['reservation']->fresh()->rental_status);
    }

    // ─── the happy path ─────────────────────────────────────────

    public function test_refunding_sends_rent_and_deposit_as_one_partial_refund_and_cancels_the_reservation(): void
    {
        $this->fakeRefund();
        $t = $this->heldGroup();
        $admin = $this->admin();

        $this->refund($admin, $t['deposit'])->assertSessionHas('success');

        $attributes = $this->sentRefund();
        $this->assertSame(600000, $attributes['amount'], 'Rent 5,000 + deposit 1,000, without the fee.');
        $this->assertSame('pay_group', $attributes['payment_id']);
        $this->assertSame('others', $attributes['reason']);
        $this->assertSame('The landlord never handed over the keys.', $attributes['notes']);
        Http::assertSentCount(1);

        foreach ([$t['rent'], $t['deposit']] as $row) {
            $row->refresh();
            $this->assertSame('Refunded', $row->status);
            $this->assertSame($admin->user_id, $row->refunded_by);
            $this->assertNotNull($row->refunded_at);
            $this->assertSame('ref_test', $row->paymongo_refund_id);
            $this->assertSame('succeeded', $row->refund_status);
            $this->assertFalse($row->refund_includes_fee);
            $this->assertNull($row->payout_owed_at, 'Refunded money is never owed to the landlord.');
        }

        $reservation = $t['reservation']->fresh();
        $this->assertSame('Cancelled', $reservation->rental_status);
        $this->assertSame('Available', $reservation->unit->availability_status);
        $this->assertTrue(Message::where('conversation_id', $reservation->conversation_id)->where('kind', 'system')
            ->where('message', 'like', '%refunded the held payment to the tenant%')->exists());
        $this->assertSame(1, Notification::where('user_id', $t['tenant']->user_id)->where('title', 'Payment refunded')->count());
        $this->assertSame(1, Notification::where('user_id', $t['landlord']->user_id)->where('title', 'Held payment refunded to the tenant')->count());
        $this->assertTrue(AuditLog::where('action', 'payment.refund')->exists());
    }

    public function test_the_fee_is_added_only_when_the_admin_ticks_it(): void
    {
        $this->fakeRefund();
        $t = $this->heldGroup();

        $this->refund($this->admin(), $t['rent'], ['include_fee' => '1'])->assertSessionHas('success');

        $this->assertSame(611000, $this->sentRefund()['amount'], 'Rent 5,000 + deposit 1,000 + fee 110.');
        $this->assertTrue($t['rent']->fresh()->refund_includes_fee);
        $this->assertTrue($t['deposit']->fresh()->refund_includes_fee);
    }

    public function test_a_single_row_payment_with_no_session_is_refunded_on_its_own(): void
    {
        $this->fakeRefund();
        $t = $this->makeTenancy(['rental_status' => 'Rental Agreement Signed']);
        $payment = $this->makePayment($t['reservation'], [
            'payment_type' => 'Initial', 'billing_period' => null, 'amount' => 5000, 'status' => 'Held',
            'payment_method' => 'GCash', 'paymongo_payment_id' => 'pay_single',
        ]);

        $this->refund($this->admin(), $payment)->assertSessionHas('success');

        $this->assertSame(500000, $this->sentRefund()['amount']);
        $this->assertSame('Refunded', $payment->fresh()->status);
    }

    public function test_a_qr_ph_payment_goes_to_its_own_host_and_the_tenant_gets_the_claim_link(): void
    {
        $this->fakeRefund('processing', ['transfer_link' => 'https://transfer.paymongo.test/claim-me'], self::QRPH_REFUNDS);
        $t = $this->heldGroup(['payment_method' => 'QRPh'], ['payment_method' => 'QRPh']);

        $this->refund($this->admin(), $t['rent'])->assertSessionHas('success');

        Http::assertSent(fn (Request $r) => $r->url() === self::QRPH_REFUNDS);
        Http::assertNotSent(fn (Request $r) => $r->url() === self::REFUNDS);
        $this->assertSame('processing', $t['rent']->fresh()->refund_status);
        $this->assertSame('https://transfer.paymongo.test/claim-me', $t['deposit']->fresh()->refund_transfer_link);
        $this->assertTrue(Notification::where('user_id', $t['tenant']->user_id)
            ->where('message', 'like', '%https://transfer.paymongo.test/claim-me%')->exists());

        // The tenant finds the link on their reservations list.
        $this->actingAs($t['tenant'])->get(route('reservations.index'))
            ->assertOk()->assertSee('Claim your refund')->assertSee('https://transfer.paymongo.test/claim-me');
    }

    public function test_money_that_arrived_after_the_reservation_ended_is_refunded_without_touching_the_reservation(): void
    {
        $this->fakeRefund();
        $t = $this->heldGroup();
        $t['reservation']->update(['rental_status' => 'Cancelled']);
        $before = Message::where('conversation_id', $t['reservation']->conversation_id)->count();

        $this->refund($this->admin(), $t['rent'])->assertSessionHas('success');

        $this->assertSame('Refunded', $t['rent']->fresh()->status);
        $this->assertSame('Cancelled', $t['reservation']->fresh()->rental_status);
        $this->assertSame($before, Message::where('conversation_id', $t['reservation']->conversation_id)->count(), 'No "cancelled" chat message for a reservation that was already over.');
    }

    // ─── what cannot be refunded ────────────────────────────────

    public function test_a_payment_that_is_not_held_is_refused_with_a_409_and_pay_mongo_is_not_asked(): void
    {
        Http::fake();
        $t = $this->heldGroup();
        Payment::whereIn('payment_id', [$t['rent']->payment_id, $t['deposit']->payment_id])->update(['status' => 'Released']);

        $this->refund($this->admin(), $t['rent'])->assertStatus(409);

        Http::assertNothingSent();
    }

    public function test_a_group_with_one_row_not_held_is_refused(): void
    {
        Http::fake();
        $t = $this->heldGroup();
        $t['deposit']->update(['status' => 'Released']);

        $this->refund($this->admin(), $t['rent'])->assertStatus(409);

        Http::assertNothingSent();
        $this->assertSame('Held', $t['rent']->fresh()->status);
    }

    public function test_an_already_refunded_payment_is_refused(): void
    {
        $this->fakeRefund();
        $t = $this->heldGroup();
        $this->refund($this->admin(), $t['rent'])->assertSessionHas('success');

        $this->refund($this->admin(), $t['rent'])->assertStatus(409);

        Http::assertSentCount(1);
    }

    public function test_money_already_owed_to_the_landlord_cannot_be_refunded(): void
    {
        Http::fake();
        $t = $this->heldGroup();
        $t['rent']->update(['payout_owed_at' => now()]);

        $this->refund($this->admin(), $t['rent'])->assertStatus(409);

        Http::assertNothingSent();
    }

    public function test_landlord_recorded_money_cannot_be_refunded(): void
    {
        Http::fake();
        $t = $this->makeTenancy();
        $payment = $this->makeRecordedPayment($t['reservation'], $t['landlord'], ['status' => 'Held', 'paymongo_payment_id' => 'pay_x']);

        $this->refund($this->admin(), $payment)->assertStatus(409);

        Http::assertNothingSent();
    }

    public function test_a_group_without_a_single_pay_mongo_payment_id_is_refused(): void
    {
        Http::fake();
        $t = $this->heldGroup(['paymongo_payment_id' => null], ['paymongo_payment_id' => null]);

        $this->refund($this->admin(), $t['rent'])->assertStatus(409);

        Http::assertNothingSent();
    }

    public function test_less_than_a_peso_is_refused_before_pay_mongo_is_asked(): void
    {
        Http::fake();
        $t = $this->makeTenancy(['rental_status' => 'Rental Agreement Signed']);
        $payment = $this->makePayment($t['reservation'], [
            'payment_type' => 'Initial', 'billing_period' => null, 'amount' => 0.5, 'status' => 'Held',
            'payment_method' => 'GCash', 'paymongo_payment_id' => 'pay_tiny',
        ]);

        $this->refund($this->admin(), $payment)->assertSessionHas('error');

        Http::assertNothingSent();
    }

    // ─── PayMongo says no ───────────────────────────────────────

    public function test_a_pay_mongo_refusal_changes_nothing_and_shows_its_reason(): void
    {
        Http::fake([self::REFUNDS => Http::response(['errors' => [['code' => 'allowed_date_exceeded', 'detail' => 'Not allowed to refund a gcash payment after 180 days.']]], 400)]);
        $t = $this->heldGroup();

        $this->refund($this->admin(), $t['rent'])
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'refund window has passed') && str_contains($m, 'Nothing was changed'));

        $this->assertNothingChanged($t);
        $this->assertSame('Reserved', $t['reservation']->unit->fresh()->availability_status);
        $this->assertSame(0, Notification::where('user_id', $t['tenant']->user_id)->where('title', 'Payment refunded')->count());
    }

    public function test_an_unreachable_pay_mongo_changes_nothing(): void
    {
        Http::fake(fn () => throw new ConnectionException('timeout'));
        $t = $this->heldGroup();

        $this->refund($this->admin(), $t['rent'])->assertSessionHas('error', fn ($m) => str_contains($m, 'could not reach PayMongo'));

        $this->assertNothingChanged($t);
    }

    public function test_a_refund_pay_mongo_reports_as_failed_is_not_recorded(): void
    {
        $this->fakeRefund('failed');
        $t = $this->heldGroup();

        $this->refund($this->admin(), $t['rent'])->assertSessionHas('error');

        $this->assertNothingChanged($t);
    }

    // ─── who may ask ────────────────────────────────────────────

    public function test_only_an_admin_can_refund(): void
    {
        Http::fake();
        $t = $this->heldGroup();

        $this->refund($t['tenant'], $t['rent'])->assertForbidden();
        $this->refund($t['landlord'], $t['rent'])->assertForbidden();

        Http::assertNothingSent();
        $this->assertNothingChanged($t);
    }

    public function test_a_reason_is_required(): void
    {
        Http::fake();
        $t = $this->heldGroup();

        $this->refund($this->admin(), $t['rent'], ['refund_reason' => ''])->assertSessionHasErrors('refund_reason');
        $this->refund($this->admin(), $t['rent'], ['refund_reason' => str_repeat('x', 256)])->assertSessionHasErrors('refund_reason');

        Http::assertNothingSent();
    }

    public function test_a_double_click_refunds_once(): void
    {
        $this->fakeRefund();
        $t = $this->heldGroup();
        $admin = $this->admin();

        $this->refund($admin, $t['rent'])->assertSessionHas('success');
        $this->refund($admin, $t['deposit'])->assertStatus(409);

        Http::assertSentCount(1);
        $this->assertSame(1, Notification::where('user_id', $t['tenant']->user_id)->where('title', 'Payment refunded')->count());
    }

    // ─── the admin screens ──────────────────────────────────────

    public function test_the_payments_list_offers_the_refund_and_lists_refunded_money(): void
    {
        $t = $this->heldGroup();
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.payments.index', ['status' => 'Held']))
            ->assertOk()->assertSee('Refund tenant')->assertSee('open-refund-payment', false);

        $this->fakeRefund('processing', ['transfer_link' => 'https://transfer.paymongo.test/x']);
        $this->refund($admin, $t['rent']);

        $this->actingAs($admin)->get(route('admin.payments.index', ['status' => 'Refunded']))
            ->assertOk()->assertSee('Waiting for the tenant to claim it');
    }

    public function test_a_failed_refund_is_flagged_in_the_list(): void
    {
        $t = $this->heldGroup();
        Payment::whereIn('payment_id', [$t['rent']->payment_id, $t['deposit']->payment_id])
            ->update(['status' => 'Refunded', 'refund_status' => 'failed', 'refunded_at' => now()]);

        $this->actingAs($this->admin())->get(route('admin.payments.index', ['status' => 'Refunded']))
            ->assertOk()->assertSee('Refund failed. The tenant has not been repaid.');
    }

    public function test_the_admin_conversation_view_offers_the_refund_for_a_held_payment(): void
    {
        $t = $this->heldGroup();

        $this->actingAs($this->admin())->get(route('admin.conversations.show', $t['reservation']->conversation_id))
            ->assertOk()->assertSee('Refund tenant');
    }

    // ─── webhook: where the refund ends up ──────────────────────

    private const SECRET = 'whsec_refund_test';

    private function deliver(array $event)
    {
        config(['services.paymongo.webhook_secret' => self::SECRET]);
        $body = json_encode($event);
        $timestamp = time();
        $signature = "t={$timestamp},te=".hash_hmac('sha256', $timestamp.'.'.$body, self::SECRET).',li=';

        return $this->call('POST', route('webhooks.paymongo'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_PAYMONGO_SIGNATURE' => $signature,
        ], $body);
    }

    private function refundedEvent(string $status, string $refundId = 'ref_test', string $type = 'payment.refunded'): array
    {
        return ['data' => ['attributes' => [
            'type' => $type,
            'data' => [
                'id' => 'pay_group',
                'type' => 'payment',
                'attributes' => ['refunds' => [['id' => $refundId, 'type' => 'refund', 'attributes' => ['status' => $status, 'payment_id' => 'pay_group']]]],
            ],
        ]]];
    }

    private function refundedGroup(string $status = 'processing'): array
    {
        $this->fakeRefund($status, $status === 'processing' ? ['transfer_link' => 'https://transfer.paymongo.test/x'] : []);
        $t = $this->heldGroup();
        $this->refund($this->admin(), $t['rent'])->assertSessionHas('success');

        return $t;
    }

    public function test_the_webhook_marks_a_claimed_refund_succeeded_and_tells_the_tenant(): void
    {
        $t = $this->refundedGroup('processing');

        $this->deliver($this->refundedEvent('succeeded'))->assertOk();

        $this->assertSame('succeeded', $t['rent']->fresh()->refund_status);
        $this->assertSame('succeeded', $t['deposit']->fresh()->refund_status);
        $this->assertTrue(Notification::where('user_id', $t['tenant']->user_id)->where('title', 'Refund sent')
            ->where('message', 'like', '%₱6,000.00%')->exists());
    }

    public function test_a_failed_refund_alerts_the_admins_once_even_if_the_event_is_replayed(): void
    {
        $admin = $this->admin();
        $t = $this->refundedGroup('processing');

        $this->deliver($this->refundedEvent('failed', 'ref_test', 'payment.refund.updated'))->assertOk();
        $this->deliver($this->refundedEvent('failed', 'ref_test', 'payment.refund.updated'))->assertOk();

        $this->assertSame('failed', $t['rent']->fresh()->refund_status);
        $this->assertSame(1, Notification::where('user_id', $admin->user_id)->where('title', 'A refund failed')->count());
    }

    public function test_a_late_older_event_does_not_reopen_a_finished_refund(): void
    {
        $t = $this->refundedGroup('processing');
        $this->deliver($this->refundedEvent('succeeded'))->assertOk();

        $this->deliver($this->refundedEvent('processing'))->assertOk();

        $this->assertSame('succeeded', $t['rent']->fresh()->refund_status);
    }

    public function test_a_refund_we_did_not_create_is_ignored(): void
    {
        $t = $this->refundedGroup('processing');

        $this->deliver($this->refundedEvent('succeeded', 'ref_from_the_dashboard'))->assertOk();

        $this->assertSame('processing', $t['rent']->fresh()->refund_status);
    }

    public function test_a_single_refund_resource_event_is_understood_too(): void
    {
        $t = $this->refundedGroup('processing');

        $this->deliver(['data' => ['attributes' => [
            'type' => 'payment.refund.updated',
            'data' => ['id' => 'ref_test', 'type' => 'refund', 'attributes' => ['status' => 'succeeded', 'payment_id' => 'pay_group']],
        ]]])->assertOk();

        $this->assertSame('succeeded', $t['rent']->fresh()->refund_status);
    }
}
