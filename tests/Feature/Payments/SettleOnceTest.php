<?php

namespace Tests\Feature\Payments;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Payment;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\Support\CreatesPaymentFixtures;
use Tests\TestCase;

/**
 * The webhook and the poll fallback can both see the same Pending row. Only the first to settle it may write
 * and announce; a replayed webhook or a second poll must change nothing, least of all rewrite a Released
 * payment back to Held.
 */
class SettleOnceTest extends TestCase
{
    use CreatesMarketplaceFixtures, CreatesPaymentFixtures, DatabaseTransactions;

    private const SECRET = 'whsec_test_secret';

    private const BASE = 'https://api.paymongo.com/v1/checkout_sessions';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.paymongo.secret_key' => 'sk_test_x', 'services.paymongo.webhook_secret' => self::SECRET]);
    }

    /** @return array{0: array, 1: Payment, 2: Conversation} */
    private function pendingInitial(string $sessionId = 'cs_1'): array
    {
        $t = $this->makeTenancy(['rental_status' => 'Rental Agreement Signed', 'agreed_at' => now(), 'agreed_ip' => '127.0.0.1']);
        $conversation = Conversation::create([
            'tenant_id'   => $t['tenant']->user_id,
            'landlord_id' => $t['landlord']->user_id,
            'property_id' => $t['reservation']->property_id,
        ]);
        $t['reservation']->update(['conversation_id' => $conversation->conversation_id]);

        return [$t, $this->makePendingCheckout($t['reservation'], $sessionId), $conversation];
    }

    private function systemMessages(Conversation $conversation): int
    {
        return Message::where('conversation_id', $conversation->conversation_id)->where('kind', 'system')->count();
    }

    private function fakePaidSession(string $sessionId): void
    {
        Http::fake([self::BASE.'/'.$sessionId => Http::response(['data' => ['attributes' => [
            'status'         => 'paid',
            'payments'       => [['id' => 'pay_1', 'attributes' => ['source' => ['type' => 'gcash']]]],
            'payment_intent' => ['id' => 'pi_1'],
        ]]], 200)]);
    }

    private function deliverWebhook(string $sessionId)
    {
        $body = json_encode(['data' => ['attributes' => [
            'type' => 'checkout_session.payment.paid',
            'data' => ['id' => $sessionId, 'attributes' => [
                'payment_intent'      => ['id' => 'pi_'.$sessionId],
                'payments'            => [['id' => 'pay_'.$sessionId]],
                'payment_method_used' => 'gcash',
            ]],
        ]]]);
        $timestamp = time();
        $signature = "t={$timestamp},te=".hash_hmac('sha256', $timestamp.'.'.$body, self::SECRET).',li=';

        return $this->call('POST', route('webhooks.paymongo'), [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_PAYMONGO_SIGNATURE' => $signature,
        ], $body);
    }

    public function test_polling_twice_settles_and_announces_once(): void
    {
        [$t, $payment, $conversation] = $this->pendingInitial();
        $this->fakePaidSession('cs_1');

        $this->actingAs($t['tenant'])->get(route('payments.success', $t['reservation']))->assertOk();
        $this->actingAs($t['tenant'])->get(route('payments.success', $t['reservation']))->assertOk();

        $this->assertSame('Held', $payment->fresh()->status);
        $this->assertSame(1, $this->systemMessages($conversation));
    }

    public function test_a_stale_pending_copy_cannot_settle_a_row_someone_else_already_settled(): void
    {
        [, $payment] = $this->pendingInitial();
        $stale = Payment::find($payment->getKey());

        $first = $payment->settlePending(['status' => 'Held', 'paid_at' => now()]);
        Payment::whereKey($payment->getKey())->update(['status' => 'Released']);
        $second = $stale->settlePending(['status' => 'Held', 'paid_at' => now()]);

        $this->assertNotNull($first);
        $this->assertNull($second, 'the second settle is a no-op');
        $this->assertSame('Released', $payment->fresh()->status, 'not rewritten from Released');
    }

    public function test_a_replayed_webhook_changes_nothing_and_posts_nothing(): void
    {
        [, $payment, $conversation] = $this->pendingInitial();

        $this->deliverWebhook('cs_1')->assertOk();
        $this->assertSame('Held', $payment->fresh()->status);
        $this->assertSame(1, $this->systemMessages($conversation));

        Payment::whereKey($payment->getKey())->update(['status' => 'Released', 'payout_owed_at' => now()]);

        $this->deliverWebhook('cs_1')->assertOk();

        $this->assertSame('Released', $payment->fresh()->status, 'a replay must not rewrite Released to Held');
        $this->assertSame(1, $this->systemMessages($conversation));
    }

    public function test_the_poll_after_the_webhook_changes_nothing(): void
    {
        [$t, $payment, $conversation] = $this->pendingInitial();
        $this->deliverWebhook('cs_1')->assertOk();
        $this->fakePaidSession('cs_1');

        $this->actingAs($t['tenant'])->get(route('payments.success', $t['reservation']))->assertOk();

        $this->assertSame('Held', $payment->fresh()->status);
        $this->assertSame(1, $this->systemMessages($conversation));
    }
}
