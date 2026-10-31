<?php

namespace Tests\Feature\Payments;

use App\Models\Notification;
use App\Models\Payment;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\Support\CreatesPaymentFixtures;
use Tests\TestCase;

/**
 * The PayMongo webhook is the one endpoint that moves money without a logged-in
 * user, so it must refuse anything unsigned, settle each payment type the right
 * way, and shrug off a retried delivery.
 */
class PayMongoWebhookTest extends TestCase
{
    use CreatesMarketplaceFixtures, CreatesPaymentFixtures, DatabaseTransactions;

    private const SECRET = 'whsec_test_secret';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.paymongo.webhook_secret' => self::SECRET]);
    }

    private function paidEvent(string $sessionId, string $type = 'checkout_session.payment.paid'): string
    {
        return json_encode(['data' => ['attributes' => [
            'type' => $type,
            'data' => [
                'id'         => $sessionId,
                'attributes' => [
                    'payment_intent'      => ['id' => 'pi_' . $sessionId],
                    'payments'            => [['id' => 'pay_' . $sessionId]],
                    'payment_method_used' => 'gcash',
                ],
            ],
        ]]]);
    }

    private function signature(string $body, string $secret = self::SECRET, ?int $timestamp = null): string
    {
        $timestamp ??= time();

        return "t={$timestamp},te=" . hash_hmac('sha256', $timestamp . '.' . $body, $secret) . ',li=';
    }

    private function deliver(string $body, ?string $signature)
    {
        $server = ['CONTENT_TYPE' => 'application/json'];

        if ($signature !== null) {
            $server['HTTP_PAYMONGO_SIGNATURE'] = $signature;
        }

        return $this->call('POST', route('webhooks.paymongo'), [], [], [], $server, $body);
    }

    public function test_a_request_without_a_signature_is_refused_and_settles_nothing(): void
    {
        $t = $this->makeTenancy();
        $payment = $this->makePendingCheckout($t['reservation'], 'cs_nosig');

        $this->deliver($this->paidEvent('cs_nosig'), null)->assertForbidden();

        $this->assertSame('Pending', $payment->fresh()->status);
    }

    public function test_a_signature_made_with_the_wrong_secret_is_refused(): void
    {
        $t = $this->makeTenancy();
        $payment = $this->makePendingCheckout($t['reservation'], 'cs_forged');
        $body = $this->paidEvent('cs_forged');

        $this->deliver($body, $this->signature($body, 'someone_elses_secret'))->assertForbidden();

        $this->assertSame('Pending', $payment->fresh()->status);
    }

    public function test_a_body_changed_after_signing_is_refused(): void
    {
        $t = $this->makeTenancy();
        $payment = $this->makePendingCheckout($t['reservation'], 'cs_tamper');
        $signature = $this->signature($this->paidEvent('cs_other'));

        $this->deliver($this->paidEvent('cs_tamper'), $signature)->assertForbidden();

        $this->assertSame('Pending', $payment->fresh()->status);
    }

    public function test_everything_is_refused_when_no_webhook_secret_is_configured(): void
    {
        config(['services.paymongo.webhook_secret' => '']);
        $t = $this->makeTenancy();
        $payment = $this->makePendingCheckout($t['reservation'], 'cs_nosecret');
        $body = $this->paidEvent('cs_nosecret');

        $this->deliver($body, $this->signature($body, ''))->assertForbidden();

        $this->assertSame('Pending', $payment->fresh()->status);
    }

    public function test_a_correctly_signed_but_stale_request_is_refused(): void
    {
        $t = $this->makeTenancy();
        $payment = $this->makePendingCheckout($t['reservation'], 'cs_stale');
        $body = $this->paidEvent('cs_stale');

        $this->deliver($body, $this->signature($body, self::SECRET, time() - 600))->assertForbidden();

        $this->assertSame('Pending', $payment->fresh()->status, 'A captured request must not be replayable later.');
    }

    public function test_a_signature_dated_in_the_future_is_refused(): void
    {
        $t = $this->makeTenancy();
        $payment = $this->makePendingCheckout($t['reservation'], 'cs_future');
        $body = $this->paidEvent('cs_future');

        $this->deliver($body, $this->signature($body, self::SECRET, time() + 600))->assertForbidden();

        $this->assertSame('Pending', $payment->fresh()->status);
    }

    public function test_a_slightly_old_signature_inside_the_window_is_accepted(): void
    {
        $t = $this->makeTenancy();
        $payment = $this->makePendingCheckout($t['reservation'], 'cs_window');
        $body = $this->paidEvent('cs_window');

        $this->deliver($body, $this->signature($body, self::SECRET, time() - 240))->assertOk();

        $this->assertSame('Held', $payment->fresh()->status);
    }

    public function test_the_window_can_be_narrowed_by_config(): void
    {
        config(['services.paymongo.webhook_tolerance' => 30]);
        $t = $this->makeTenancy();
        $payment = $this->makePendingCheckout($t['reservation'], 'cs_narrow');
        $body = $this->paidEvent('cs_narrow');

        $this->deliver($body, $this->signature($body, self::SECRET, time() - 120))->assertForbidden();

        $this->assertSame('Pending', $payment->fresh()->status);
    }

    public function test_a_malformed_signature_header_is_refused_not_a_server_error(): void
    {
        $t = $this->makeTenancy();
        $payment = $this->makePendingCheckout($t['reservation'], 'cs_garbage');
        $body = $this->paidEvent('cs_garbage');

        foreach (['garbage', 't=abc,te=deadbeef', 't=,te=', ',,,', 'te=' . str_repeat('a', 64)] as $header) {
            $this->deliver($body, $header)->assertForbidden();
        }

        $this->assertSame('Pending', $payment->fresh()->status);
    }

    public function test_a_paid_initial_payment_goes_into_escrow_not_to_the_landlord(): void
    {
        $t = $this->makeTenancy(['rental_status' => 'Rental Agreement Signed']);
        $payment = $this->makePendingCheckout($t['reservation'], 'cs_initial');
        $body = $this->paidEvent('cs_initial');

        $this->deliver($body, $this->signature($body))->assertOk();

        $payment->refresh();
        $this->assertSame('Held', $payment->status);
        $this->assertNull($payment->payout_status, 'Escrowed money must not be payout-eligible until released.');
        $this->assertNotNull($payment->paid_at);
        $this->assertSame('GCash', $payment->payment_method);
        $this->assertSame('pi_cs_initial', $payment->paymongo_payment_intent_id);
        $this->assertSame('pay_cs_initial', $payment->paymongo_payment_id);
    }

    public function test_paid_monthly_rent_settles_straight_to_the_landlord(): void
    {
        $t = $this->makeTenancy();
        $payment = $this->makePendingCheckout($t['reservation'], 'cs_rent', [
            'payment_type'   => 'Monthly',
            'billing_period' => '2026-09-01',
        ]);
        $body = $this->paidEvent('cs_rent');

        $this->deliver($body, $this->signature($body))->assertOk();

        $payment->refresh();
        $this->assertSame('Paid', $payment->status);
        $this->assertSame('Pending Payout', $payment->payout_status);
    }

    public function test_a_retried_delivery_does_not_settle_twice(): void
    {
        $t = $this->makeTenancy(['rental_status' => 'Rental Agreement Signed']);
        $payment = $this->makePendingCheckout($t['reservation'], 'cs_retry');
        $body = $this->paidEvent('cs_retry');

        $this->deliver($body, $this->signature($body))->assertOk();
        $firstPaidAt = $payment->fresh()->paid_at;
        $notifications = Notification::count();

        $this->travel(2)->hours();
        $this->deliver($body, $this->signature($body))->assertOk();

        $payment->refresh();
        $this->assertSame('Held', $payment->status);
        $this->assertEquals($firstPaidAt, $payment->paid_at, 'A retry must not rewrite the payment time.');
        $this->assertSame($notifications, Notification::count(), 'A retry must not notify anyone again.');
    }

    public function test_a_replay_cannot_pull_an_already_released_payment_back_to_held(): void
    {
        $t = $this->makeTenancy();
        $payment = $this->makePendingCheckout($t['reservation'], 'cs_released', [
            'status'        => 'Released',
            'payout_status' => 'Pending Payout',
            'paid_at'       => now()->subDay(),
        ]);
        $body = $this->paidEvent('cs_released');

        $this->deliver($body, $this->signature($body))->assertOk();

        $payment->refresh();
        $this->assertSame('Released', $payment->status);
        $this->assertSame('Pending Payout', $payment->payout_status);
    }

    public function test_an_unknown_checkout_session_is_acknowledged_and_changes_nothing(): void
    {
        $t = $this->makeTenancy();
        $payment = $this->makePendingCheckout($t['reservation'], 'cs_known');
        $body = $this->paidEvent('cs_unknown');

        $this->deliver($body, $this->signature($body))->assertOk();

        $this->assertSame('Pending', $payment->fresh()->status);
    }

    public function test_other_event_types_are_acknowledged_but_do_not_settle_anything(): void
    {
        $t = $this->makeTenancy();
        $payment = $this->makePendingCheckout($t['reservation'], 'cs_failed');
        $body = $this->paidEvent('cs_failed', 'checkout_session.payment.failed');

        $this->deliver($body, $this->signature($body))->assertOk();

        $this->assertSame('Pending', $payment->fresh()->status);
        $this->assertSame(1, Payment::where('paymongo_checkout_session_id', 'cs_failed')->count());
    }

    // ─── live mode and the paid amount ──────────────────────────

    /** A paid event, optionally flagged live and carrying the centavo amount PayMongo took. */
    private function modeEvent(string $sessionId, bool $live, ?int $paidCentavos = null): string
    {
        $event = json_decode($this->paidEvent($sessionId), true);
        $event['data']['attributes']['livemode'] = $live;

        if ($paidCentavos !== null) {
            $event['data']['attributes']['data']['attributes']['payments'][0]['attributes']['amount'] = $paidCentavos;
        }

        return json_encode($event);
    }

    private function liveSignature(string $body, string $secret = self::SECRET): string
    {
        $timestamp = time();

        return "t={$timestamp},te=,li=" . hash_hmac('sha256', $timestamp . '.' . $body, $secret);
    }

    public function test_a_live_event_signed_in_the_li_field_settles_the_payment(): void
    {
        $t = $this->makeTenancy(['rental_status' => 'Rental Agreement Signed']);
        $payment = $this->makePendingCheckout($t['reservation'], 'cs_live');
        $body = $this->modeEvent('cs_live', true);

        $this->deliver($body, $this->liveSignature($body))->assertOk();

        $this->assertSame('Held', $payment->fresh()->status);
    }

    public function test_a_live_event_carrying_only_a_test_signature_is_refused(): void
    {
        $t = $this->makeTenancy(['rental_status' => 'Rental Agreement Signed']);
        $payment = $this->makePendingCheckout($t['reservation'], 'cs_live_te');
        $body = $this->modeEvent('cs_live_te', true);

        $this->deliver($body, $this->signature($body))->assertForbidden();

        $this->assertSame('Pending', $payment->fresh()->status);
    }

    public function test_a_test_event_carrying_only_a_live_signature_is_refused(): void
    {
        $t = $this->makeTenancy(['rental_status' => 'Rental Agreement Signed']);
        $payment = $this->makePendingCheckout($t['reservation'], 'cs_test_li');
        $body = $this->modeEvent('cs_test_li', false);

        $this->deliver($body, $this->liveSignature($body))->assertForbidden();

        $this->assertSame('Pending', $payment->fresh()->status);
    }

    public function test_a_payment_for_the_amount_asked_is_settled(): void
    {
        $t = $this->makeTenancy(['rental_status' => 'Rental Agreement Signed']);
        $payment = $this->makePendingCheckout($t['reservation'], 'cs_exact', ['amount' => 5000]);
        $body = $this->modeEvent('cs_exact', false, 500000);

        $this->deliver($body, $this->signature($body))->assertOk();

        $this->assertSame('Held', $payment->fresh()->status);
    }

    public function test_a_different_amount_is_left_pending_and_the_admins_are_told_once(): void
    {
        $admin = $this->makeUserWithRole('Admin');
        $t = $this->makeTenancy(['rental_status' => 'Rental Agreement Signed']);
        $payment = $this->makePendingCheckout($t['reservation'], 'cs_short', ['amount' => 5000]);
        $body = $this->modeEvent('cs_short', false, 100000);

        $this->deliver($body, $this->signature($body))->assertOk();
        $this->deliver($body, $this->signature($body))->assertOk();

        $this->assertSame('Pending', $payment->fresh()->status, 'A payment for the wrong amount must not be settled.');
        $this->assertNull($payment->fresh()->paid_at);
        $this->assertSame(1, Notification::where('user_id', $admin->user_id)->where('title', 'Online payment amount does not match')->count());
    }
}
