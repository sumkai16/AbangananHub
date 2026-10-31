<?php

namespace Tests\Feature\Payments;

use App\Models\Notification;
use App\Models\Payment;
use App\Models\Reservation;
use App\Services\RentLedger;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\Support\CreatesPaymentFixtures;
use Tests\TestCase;

/**
 * An online move-in is one PayMongo payment but two rows here (rent `Initial` + `Deposit`) on one checkout session.
 * Everything that moves the money must move both rows or neither: settle (webhook and poll), the amount check,
 * clearing an abandoned checkout, and every way out of escrow (tenant confirms, the timer, an admin).
 */
class CheckoutGroupTest extends TestCase
{
    use CreatesMarketplaceFixtures, CreatesPaymentFixtures, DatabaseTransactions;

    private const SECRET = 'whsec_group_secret';

    private const BASE = 'https://api.paymongo.com/v1/checkout_sessions';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.paymongo.webhook_secret' => self::SECRET, 'services.paymongo.secret_key' => 'sk_test_x']);
        Carbon::setTestNow('2026-10-14 10:00:00');
    }

    /**
     * A signed agreement with a move-in checkout waiting on PayMongo: rent 5,000 (carrying a 110 fee) and a
     * 3,000 deposit, both Pending on $session.
     *
     * @return array{landlord: \App\Models\User, tenant: \App\Models\User, reservation: Reservation, rent: Payment, deposit: Payment}
     */
    private function pendingGroup(string $session = 'cs_group', string $status = 'Pending'): array
    {
        $t = $this->makeOnlineTenancy(['rental_status' => 'Rental Agreement Signed', 'agreed_at' => now()]);

        $t['rent'] = $this->makePendingCheckout($t['reservation'], $session, ['amount' => 5000, 'service_fee' => 110, 'status' => $status]);
        $t['deposit'] = $this->makePendingCheckout($t['reservation'], $session, [
            'payment_type' => 'Deposit', 'amount' => 3000, 'service_fee' => 0, 'status' => $status,
        ]);

        return $t;
    }

    private function heldGroup(array $reservation = []): array
    {
        $t = $this->pendingGroup('cs_held');
        foreach ([$t['rent'], $t['deposit']] as $row) {
            $row->update(['status' => 'Held', 'paid_at' => now(), 'paymongo_payment_id' => 'pay_held']);
        }
        $t['reservation']->update($reservation);

        return $t;
    }

    private function paidEvent(string $session, ?int $centavos, array $payments = null): string
    {
        return json_encode(['data' => ['attributes' => [
            'type' => 'checkout_session.payment.paid',
            'livemode' => false,
            'data' => ['id' => $session, 'attributes' => [
                'payment_intent' => ['id' => 'pi_'.$session],
                'payments' => $payments ?? [['id' => 'pay_'.$session, 'attributes' => ['status' => 'paid', 'amount' => $centavos]]],
                'payment_method_used' => 'gcash',
            ]],
        ]]]);
    }

    private function deliver(string $body)
    {
        $ts = time();
        $signature = "t={$ts},te=".hash_hmac('sha256', $ts.'.'.$body, self::SECRET).',li=';

        return $this->call('POST', route('webhooks.paymongo'), [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_PAYMONGO_SIGNATURE' => $signature,
        ], $body);
    }

    private function statuses(Reservation $r): array
    {
        return Payment::where('reservation_id', $r->reservation_id)->orderBy('payment_id')->pluck('status')->all();
    }

    // ─── settling ───────────────────────────────────────────────

    public function test_the_webhook_settles_rent_and_deposit_together_with_the_same_paymongo_ids(): void
    {
        $t = $this->pendingGroup();

        // 5,000 + 3,000 + 110 fee = 8,110 pesos.
        $this->deliver($this->paidEvent('cs_group', 811000))->assertOk();

        $this->assertSame(['Held', 'Held'], $this->statuses($t['reservation']));
        foreach ([$t['rent'], $t['deposit']] as $row) {
            $row = $row->fresh();
            $this->assertSame('pay_cs_group', $row->paymongo_payment_id);
            $this->assertSame('pi_cs_group', $row->paymongo_payment_intent_id);
            $this->assertNotNull($row->paid_at);
            $this->assertNull($row->payout_owed_at, 'Escrowed money is not payable to the landlord yet.');
        }
    }

    public function test_the_payment_the_webhook_remembers_is_the_one_that_was_paid(): void
    {
        $t = $this->pendingGroup();
        $body = $this->paidEvent('cs_group', null, [
            ['id' => 'pay_failed_try', 'attributes' => ['status' => 'failed', 'amount' => 811000]],
            ['id' => 'pay_real', 'attributes' => ['status' => 'paid', 'amount' => 811000]],
        ]);

        $this->deliver($body)->assertOk();

        $this->assertSame('pay_real', $t['rent']->fresh()->paymongo_payment_id, 'A refund is made against the paid pay_ id.');
        $this->assertSame('pay_real', $t['deposit']->fresh()->paymongo_payment_id);
    }

    public function test_the_parties_are_told_once_for_the_whole_checkout_with_the_held_total(): void
    {
        $t = $this->pendingGroup();

        $this->deliver($this->paidEvent('cs_group', 811000))->assertOk();

        $tenantNotes = Notification::where('user_id', $t['tenant']->user_id)->where('title', 'Payment received')->get();
        $this->assertCount(1, $tenantNotes, 'Not one per row.');
        $this->assertStringContainsString('₱8,000.00', $tenantNotes->first()->message, 'Rent + deposit are held; the fee is not.');
        $this->assertSame(1, Notification::where('user_id', $t['landlord']->user_id)->where('title', 'Tenant completed payment')->count());
    }

    public function test_the_amount_check_compares_with_the_whole_checkout_including_the_fee(): void
    {
        $t = $this->pendingGroup();

        // Rent alone (5,110) is not what this checkout asked for.
        $this->deliver($this->paidEvent('cs_group', 511000))->assertOk();
        $this->assertSame(['Pending', 'Pending'], $this->statuses($t['reservation']));

        // The exact total settles it.
        $this->deliver($this->paidEvent('cs_group', 811000))->assertOk();
        $this->assertSame(['Held', 'Held'], $this->statuses($t['reservation']));
    }

    public function test_a_second_delivery_settles_nothing_twice(): void
    {
        $t = $this->pendingGroup();
        $body = $this->paidEvent('cs_group', 811000);

        $this->deliver($body)->assertOk();
        $paidAt = $t['rent']->fresh()->paid_at;
        Carbon::setTestNow(now()->addMinutes(2));
        $this->deliver($body)->assertOk();

        $this->assertEquals($paidAt, $t['rent']->fresh()->paid_at);
        $this->assertSame(1, Notification::where('user_id', $t['tenant']->user_id)->where('title', 'Payment received')->count());
    }

    public function test_the_poll_fallback_settles_the_whole_group_too(): void
    {
        $t = $this->pendingGroup();
        Http::fake([self::BASE.'/cs_group' => Http::response(['data' => ['id' => 'cs_group', 'attributes' => [
            'status' => 'active',
            'payment_intent' => ['id' => 'pi_1'],
            'payments' => [['id' => 'pay_1', 'attributes' => ['status' => 'paid', 'amount' => 811000, 'source' => ['type' => 'qrph']]]],
        ]]], 200)]);

        $this->actingAs($t['tenant'])->get(route('payments.success', $t['reservation']))->assertOk()
            ->assertSee('Security deposit')->assertSee('Total paid');

        $this->assertSame(['Held', 'Held'], $this->statuses($t['reservation']));
        $this->assertSame(['QRPh', 'QRPh'], Payment::where('reservation_id', $t['reservation']->reservation_id)->orderBy('payment_id')->pluck('payment_method')->all());
    }

    // ─── an abandoned checkout ──────────────────────────────────

    public function test_an_abandoned_group_is_cleared_whole_and_the_tenant_can_pay_again(): void
    {
        $t = $this->pendingGroup('cs_old');
        Payment::where('reservation_id', $t['reservation']->reservation_id)->update(['created_at' => now()->subMinutes(45)]);
        $t['reservation']->unit->update(['security_deposit' => 3000]);

        Http::fake([
            self::BASE.'/cs_old/expire' => Http::response(['data' => ['id' => 'cs_old']], 200),
            self::BASE.'/cs_old' => Http::response(['data' => ['attributes' => ['status' => 'active', 'payments' => []]]], 200),
            self::BASE => Http::response(['data' => ['id' => 'cs_new', 'attributes' => ['checkout_url' => 'https://checkout.paymongo.test/cs_new']]], 200),
        ]);

        $this->actingAs($t['tenant'])->post(route('payments.checkout', $t['reservation']))->assertRedirect('https://checkout.paymongo.test/cs_new');

        $this->assertSame(0, Payment::where('paymongo_checkout_session_id', 'cs_old')->count(), 'Both old rows are gone, not just one.');
        $this->assertSame(2, Payment::where('paymongo_checkout_session_id', 'cs_new')->count());
        Http::assertSentCount(3);
    }

    // ─── leaving escrow ─────────────────────────────────────────

    public function test_the_tenant_confirming_move_in_releases_both_rows_and_owes_both_to_the_landlord(): void
    {
        $t = $this->heldGroup(['keys_turned_over_at' => now()->subDay(), 'move_in_deadline_at' => now()->addDays(6)]);

        $this->actingAs($t['tenant'])->post(route('agreements.confirmMoveIn', $t['reservation']))->assertSessionHasNoErrors();

        $this->assertSame(['Released', 'Released'], $this->statuses($t['reservation']));
        foreach ([$t['rent'], $t['deposit']] as $row) {
            $row = $row->fresh();
            $this->assertSame('tenant_confirmed', $row->release_reason);
            $this->assertNotNull($row->payout_owed_at);
        }
        $this->assertSame(8000.0, (float) Payment::where('reservation_id', $t['reservation']->reservation_id)->pendingPayout()->sum('amount'),
            'The payout is rent + deposit; the 110 fee is not the landlord\'s.');
        $this->assertSame('Occupied', $t['reservation']->fresh()->rental_status);
    }

    public function test_the_timer_releases_both_rows(): void
    {
        $t = $this->heldGroup(['keys_turned_over_at' => now()->subDays(8), 'move_in_deadline_at' => today()->subDay()->addHours(9)]);

        Artisan::call('reservations:process-move-in-deadlines');

        $this->assertSame(['Released', 'Released'], $this->statuses($t['reservation']));
        $this->assertSame('auto_expiry', $t['deposit']->fresh()->release_reason);
        $this->assertNotNull($t['deposit']->fresh()->payout_owed_at);
    }

    public function test_an_admin_pressing_release_on_either_row_releases_both(): void
    {
        $t = $this->heldGroup(['keys_turned_over_at' => now()->subDay(), 'move_in_deadline_at' => now()->addDays(6)]);
        $admin = $this->makeUserWithRole('Admin');

        $this->actingAs($admin)->post(route('admin.payments.release', $t['deposit']))->assertSessionHasNoErrors();

        $this->assertSame(['Released', 'Released'], $this->statuses($t['reservation']));
        $this->assertSame($admin->user_id, $t['rent']->fresh()->released_by);
        $this->assertSame('admin_manual', $t['rent']->fresh()->release_reason);
    }

    // ─── what the rest of the app sees ──────────────────────────

    public function test_cancel_is_blocked_by_the_whole_held_amount(): void
    {
        $t = $this->heldGroup();

        $this->assertStringContainsString('₱8,000.00', $t['reservation']->fresh()->cancellationBlockedReason());
        $this->assertSame(8000.0, $t['reservation']->fresh()->heldTotal());
    }

    public function test_the_ledger_reads_the_rent_as_the_first_month_and_the_deposit_as_collected(): void
    {
        $t = $this->heldGroup(['rental_status' => 'Occupied', 'target_move_in_date' => '2026-10-01', 'reservation_date' => '2026-10-01']);

        $summary = RentLedger::for($t['reservation']->fresh())->summary();

        $this->assertSame(3000.0, (float) $summary['depositCollected'], 'The held deposit counts once.');
        $this->assertSame(1, RentLedger::for($t['reservation']->fresh())->otherCharges()->count());
    }

    public function test_the_online_fee_is_never_part_of_what_the_landlord_earned(): void
    {
        $t = $this->heldGroup();

        $this->assertSame(8000.0, (float) Payment::where('reservation_id', $t['reservation']->reservation_id)->sum('amount'));
        $this->assertSame(110.0, (float) Payment::where('reservation_id', $t['reservation']->reservation_id)->sum('service_fee'));
        $this->assertSame(8110.0, $t['rent']->fresh()->checkoutTotal());
    }
}
