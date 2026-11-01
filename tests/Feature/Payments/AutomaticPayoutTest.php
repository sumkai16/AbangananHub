<?php

namespace Tests\Feature\Payments;

use App\Mail\GcashChangedMail;
use App\Jobs\SendLandlordPayout;
use App\Models\AuditLog;
use App\Models\Notification;
use App\Models\Payment;
use App\Models\Payout;
use App\Models\User;
use App\Models\UserPayoutAccount;
use App\Services\LandlordPayouts;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\Support\CreatesPaymentFixtures;
use Tests\TestCase;

/**
 * Payouts to landlords go out by themselves through PayMongo Send Money: right after a payment becomes payable,
 * with an hourly sweep behind it. Pinned here:
 *   - one transfer per landlord, never twice for the same payment (the payout row and its links exist before
 *     the HTTP call, and an answer we cannot read leaves the payout Processing instead of being re-sent);
 *   - the amount sent is what the landlord is owed, never the tenant's online fee;
 *   - money waits (stays owed) while PayMongo is not configured, the landlord has no GCash, is paused, changed
 *     their GCash within 48 hours, or has a failed payout nobody has looked at;
 *   - transfer results arrive by webhook or by the sweep's poll;
 *   - changing the GCash details needs the password, holds payouts and tells the landlord.
 * PayMongo is faked with Http::fake(); nothing here touches the network.
 */
class AutomaticPayoutTest extends TestCase
{
    use CreatesMarketplaceFixtures, CreatesPaymentFixtures, DatabaseTransactions;

    private const ENDPOINT = 'https://api.paymongo.com/v2/batch_transfers';

    private const SECRET = 'whsec_payout_test';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.paymongo.secret_key' => 'sk_test_x',
            'services.paymongo.webhook_secret' => self::SECRET,
            'services.paymongo.wallet_account_number' => '1234567890',
            'services.paymongo.wallet_account_name' => 'AbangananHub Inc',
            'services.paymongo.gcash_bic' => 'GXCHPHM2XXX',
        ]);
        Carbon::setTestNow('2026-10-31 10:00:00');
    }

    // ─── helpers ────────────────────────────────────────────────

    /** A tenancy whose landlord has GCash on file, and a payment owed to them. */
    private function owed(float $amount = 5000, array $payment = []): array
    {
        $t = $this->makeOnlineTenancy();
        $t['payment'] = $this->makePayment($t['reservation'], array_merge([
            'amount' => $amount, 'payout_status' => 'Pending Payout', 'payment_method' => 'GCash',
        ], $payment));

        return $t;
    }

    private function fakeTransfer(string $status = 'pending', string $id = 'tr_abc123'): void
    {
        Http::fake([
            self::ENDPOINT => Http::response(['data' => [
                'id' => 'batch_tr_1',
                'transfers' => [['id' => $id, 'status' => $status, 'amount' => 500000]],
            ]], 200),
            // The read-back that confirms a "succeeded" or "failed" answer before it is believed.
            'api.paymongo.com/v2/transfers/*' => Http::response(['data' => ['id' => $id, 'status' => $status, 'amount' => 500000]], 200),
        ]);
    }

    private function sent(): \Illuminate\Support\Collection
    {
        return Http::recorded(fn (Request $r) => $r->url() === self::ENDPOINT)->map(fn ($pair) => $pair[0]);
    }

    private function transferEvent(string $event, string $transferId, string $status, ?string $error = null): string
    {
        return json_encode(['data' => ['attributes' => [
            'type' => $event,
            'livemode' => false,
            'data' => ['id' => 'wallet_tr_1', 'type' => 'wallet_transaction', 'attributes' => [
                'transfer_id' => $transferId, 'status' => $status, 'provider_error' => $error,
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

    // ─── sending ────────────────────────────────────────────────

    public function test_batch_links_everything_owed_to_one_processing_payout_and_a_second_batch_finds_nothing(): void
    {
        $t = $this->owed(5000);
        $second = $this->makePayment($t['reservation'], ['amount' => 1200, 'billing_period' => '2026-09-01', 'payout_status' => 'Pending Payout']);

        $payout = LandlordPayouts::batch($t['landlord']);

        $this->assertSame('Processing', $payout->status);
        $this->assertSame('6200.00', $payout->amount);
        $this->assertSame('09171234567', $payout->gcash_number);
        $this->assertSame($payout->payout_id, $t['payment']->fresh()->payout_id);
        $this->assertSame($payout->payout_id, $second->fresh()->payout_id);
        $this->assertNull(LandlordPayouts::batch($t['landlord']), 'The same payments must never be batched twice.');
    }

    public function test_a_payment_becoming_payable_sends_the_payout_at_once_and_only_the_rent_amount(): void
    {
        $this->fakeTransfer();
        $t = $this->makeOnlineTenancy();
        // The tenant paid ₱40 on top of the ₱5,000 rent; the landlord is owed the rent only.
        $payment = $this->makePayment($t['reservation'], ['amount' => 5000, 'service_fee' => 40, 'status' => 'Held', 'payment_type' => 'Initial', 'billing_period' => null]);

        $payment->update(['status' => 'Released', 'payout_owed_at' => now()]);

        $this->assertCount(1, $this->sent());
        $transfer = $this->sent()->first()->data()['transfers'][0];
        $this->assertSame(500000, $transfer['amount'], 'Centavos, without the fee.');
        $this->assertSame('PHP', $transfer['currency']);
        $this->assertSame('instapay', $transfer['provider']);
        $this->assertSame('09171234567', $transfer['destination_account']['number']);
        $this->assertSame('Test Landlord', $transfer['destination_account']['name']);
        $this->assertSame('GXCHPHM2XXX', $transfer['destination_account']['bic']);
        $this->assertSame('1234567890', $transfer['source_account']['number']);
        $this->assertSame('PAEYPHM2XXX', $transfer['source_account']['bic']);
        $this->assertSame(route('webhooks.paymongo'), $transfer['callback_url']);

        $payout = Payout::sole();
        $this->assertSame('Processing', $payout->status);
        $this->assertSame('tr_abc123', $payout->paymongo_transfer_id);
        $this->assertSame('payout-'.$payout->payout_id, $transfer['reference_number']);
        $this->assertSame($payout->payout_id, $payment->fresh()->payout_id);
        $this->assertSame('Payout Processing', $payment->fresh()->load('payout')->payout_status);
    }

    public function test_payments_becoming_payable_one_after_another_are_each_sent_once(): void
    {
        $this->fakeTransfer();
        $t = $this->makeOnlineTenancy();
        $a = $this->makePayment($t['reservation'], ['amount' => 3000, 'billing_period' => '2026-08-01']);
        $b = $this->makePayment($t['reservation'], ['amount' => 2000, 'billing_period' => '2026-09-01']);

        $a->update(['payout_owed_at' => now()]);
        $b->update(['payout_owed_at' => now()]);

        // The first job sent ₱3,000 and the second only its own ₱2,000: nothing was paid twice. (Two jobs running at
        // the same instant are serialised by the per-landlord lock and batch()'s row lock.)
        $this->assertSame(2, Payout::count());
        $this->assertSame(0, Payment::pendingPayout()->count());
        $this->assertSame([300000, 200000], $this->sent()->map(fn ($r) => $r->data()['transfers'][0]['amount'])->all());
        $this->assertNotSame($a->fresh()->payout_id, $b->fresh()->payout_id);
    }

    public function test_an_amount_over_the_instapay_cap_goes_by_pesonet(): void
    {
        $this->fakeTransfer();
        $t = $this->owed(60000);

        LandlordPayouts::sendFor($t['landlord']);

        $this->assertSame('pesonet', $this->sent()->first()->data()['transfers'][0]['provider']);
    }

    public function test_a_transfer_that_succeeds_at_once_is_marked_sent_and_the_landlord_is_told(): void
    {
        $this->fakeTransfer('succeeded');
        $t = $this->owed();

        LandlordPayouts::sendFor($t['landlord']);

        $payout = Payout::sole();
        $this->assertSame('Sent', $payout->status);
        $this->assertNotNull($payout->paid_out_at);
        $this->assertSame('tr_abc123', $payout->payout_reference);
        $this->assertSame('Paid Out', $t['payment']->fresh()->payout_status);
        $this->assertTrue(Notification::where('user_id', $t['landlord']->user_id)->where('title', 'Payout sent')->exists());
    }

    public function test_money_waits_when_paymongo_is_not_set_up_and_the_admins_are_told_once(): void
    {
        config(['services.paymongo.gcash_bic' => null]);
        Http::fake();
        $admin = $this->makeUserWithRole('Admin');
        $t = $this->owed();

        LandlordPayouts::sendFor($t['landlord']);
        LandlordPayouts::sendFor($t['landlord']);

        Http::assertNothingSent();
        $this->assertSame(0, Payout::count());
        $this->assertNull($t['payment']->fresh()->payout_id, 'The money stays owed.');
        $alerts = Notification::where('user_id', $admin->user_id)->where('title', 'Automatic payouts need setting up');
        $this->assertSame(1, $alerts->count());
        $this->assertStringContainsString('PAYMONGO_GCASH_BIC', $alerts->first()->message);
    }

    // ─── when PayMongo does not give a clear answer ─────────────

    public function test_an_unreadable_answer_leaves_the_payout_processing_and_is_never_sent_again(): void
    {
        Http::fake([self::ENDPOINT => Http::response('boom', 500)]);
        $admin = $this->makeUserWithRole('Admin');
        $t = $this->owed();

        LandlordPayouts::sendFor($t['landlord']);
        $this->artisan('payouts:send')->assertSuccessful();
        LandlordPayouts::sendFor($t['landlord']);

        $payout = Payout::sole();
        $this->assertSame('Processing', $payout->status);
        $this->assertNull($payout->paymongo_transfer_id);
        $this->assertStringContainsString('Outcome unknown', $payout->failure_reason);
        $this->assertSame($payout->payout_id, $t['payment']->fresh()->payout_id, 'The payment stays linked, so nothing can send it again.');
        $this->assertCount(1, $this->sent(), 'No automatic resend.');
        $this->assertTrue(Notification::where('user_id', $admin->user_id)->where('title', 'A payout needs checking')->exists());
    }

    public function test_a_timeout_is_treated_the_same_way(): void
    {
        Http::fake(fn () => throw new ConnectionException('timed out'));
        $t = $this->owed();

        LandlordPayouts::sendFor($t['landlord']);

        $this->assertSame('Processing', Payout::sole()->status);
        $this->assertSame(Payout::sole()->payout_id, $t['payment']->fresh()->payout_id);
    }

    public function test_a_clear_refusal_fails_the_payout_frees_the_money_and_blocks_the_landlord_until_an_admin_retries(): void
    {
        // Refused first, accepted on the admin's retry. (Http::fake keeps the first stub that matches, so a sequence.)
        Http::fake([self::ENDPOINT => Http::sequence()
            ->push(['errors' => [['code' => 'parameter_format_invalid', 'detail' => 'Account number is invalid']]], 422)
            ->push(['data' => ['id' => 'batch_tr_1', 'transfers' => [['id' => 'tr_abc123', 'status' => 'pending']]]], 200)]);
        $admin = $this->makeUserWithRole('Admin');
        $t = $this->owed();

        LandlordPayouts::sendFor($t['landlord']);

        $payout = Payout::sole();
        $this->assertSame('Failed', $payout->status);
        $this->assertStringContainsString('Account number is invalid', $payout->failure_reason);
        $this->assertNull($t['payment']->fresh()->payout_id, 'Owed again.');
        $this->assertNotNull($t['payment']->fresh()->payout_owed_at);
        $this->assertTrue(Notification::where('user_id', $admin->user_id)->where('title', 'A payout failed')->exists());

        // The sweep must not keep hammering a destination that fails.
        $this->artisan('payouts:send')->assertSuccessful();
        $this->assertCount(1, $this->sent());
        $this->assertStringContainsString('failed payout', LandlordPayouts::blockedReason($t['landlord']));

        // The admin looks and retries: it is allowed again.
        $this->actingAs($admin)->post(route('admin.payouts.retry', $payout))->assertRedirect();

        $this->assertNotNull($payout->fresh()->resolved_at);
        $this->assertSame(2, Payout::count());
        $this->assertSame('Processing', Payout::latest('payout_id')->first()->status);
        $this->assertCount(2, $this->sent());
        $this->assertTrue(AuditLog::where('action', 'payout.retry')->exists());
    }

    public function test_a_short_wallet_cancels_quietly_and_the_money_stays_owed(): void
    {
        Http::fake([self::ENDPOINT => Http::response(['errors' => [['code' => 'resource_failed_state', 'detail' => 'Insufficient wallet balance']]], 400)]);
        $t = $this->owed();

        LandlordPayouts::sendFor($t['landlord']);

        $this->assertSame(0, Payout::count(), 'No Failed record piles up for an hour of low balance.');
        $this->assertNull($t['payment']->fresh()->payout_id);
        $this->assertNull(LandlordPayouts::blockedReason($t['landlord']));
    }

    public function test_a_stuck_payout_without_a_transfer_id_is_only_retried_after_the_admin_confirms(): void
    {
        Http::fake([self::ENDPOINT => Http::sequence()
            ->push('boom', 500)
            ->push(['data' => ['id' => 'batch_tr_1', 'transfers' => [['id' => 'tr_abc123', 'status' => 'pending']]]], 200)]);
        $admin = $this->makeUserWithRole('Admin');
        $t = $this->owed();
        LandlordPayouts::sendFor($t['landlord']);
        $payout = Payout::sole();

        $this->actingAs($admin)->post(route('admin.payouts.retry', $payout))->assertStatus(422);
        $this->assertSame('Processing', $payout->fresh()->status);
        $this->assertCount(1, $this->sent());

        $this->actingAs($admin)->post(route('admin.payouts.retry', $payout), ['confirmed' => 1])->assertRedirect();

        $this->assertSame('Failed', $payout->fresh()->status);
        $this->assertNotNull($payout->fresh()->resolved_at);
        $this->assertSame('Processing', Payout::latest('payout_id')->first()->status);
        $this->assertSame('tr_abc123', Payout::latest('payout_id')->first()->paymongo_transfer_id, 'The retry really reached PayMongo.');
        $this->assertCount(2, $this->sent());
    }

    // ─── results ────────────────────────────────────────────────

    public function test_the_success_webhook_marks_the_payout_sent(): void
    {
        $this->fakeTransfer();
        $t = $this->owed();
        LandlordPayouts::sendFor($t['landlord']);

        $this->deliver($this->transferEvent('transfer.outward.successful', 'tr_abc123', 'succeeded'))->assertOk();

        $payout = Payout::sole();
        $this->assertSame('Sent', $payout->status);
        $this->assertNotNull($payout->paid_out_at);
        $this->assertSame('Paid Out', $t['payment']->fresh()->payout_status);
    }

    public function test_the_failure_webhook_fails_the_payout_and_frees_the_money(): void
    {
        $this->fakeTransfer();
        $t = $this->owed();
        LandlordPayouts::sendFor($t['landlord']);

        $this->deliver($this->transferEvent('transfer.outward.failed', 'tr_abc123', 'failed', 'Invalid account'))->assertOk();

        $payout = Payout::sole();
        $this->assertSame('Failed', $payout->status);
        $this->assertSame('Invalid account', $payout->failure_reason);
        $this->assertNull($t['payment']->fresh()->payout_id);
    }

    public function test_a_transfer_event_for_a_payout_that_is_already_settled_changes_nothing(): void
    {
        $this->fakeTransfer('succeeded');
        $t = $this->owed();
        LandlordPayouts::sendFor($t['landlord']);

        $this->deliver($this->transferEvent('transfer.outward.failed', 'tr_abc123', 'failed'))->assertOk();

        $this->assertSame('Sent', Payout::sole()->status);
        $this->assertNotNull($t['payment']->fresh()->payout_id);
    }

    public function test_events_about_transfers_we_do_not_know_are_acknowledged_and_ignored(): void
    {
        $this->deliver($this->transferEvent('transfer.outward.successful', 'tr_someone_elses', 'succeeded'))->assertOk();
        $this->deliver($this->transferEvent('transfer.inward.successful', 'tr_in', 'succeeded'))->assertOk();

        $this->assertSame(0, Payout::count());
    }

    public function test_the_sweep_polls_old_unconfirmed_transfers_and_leaves_fresh_ones(): void
    {
        $this->fakeTransfer();
        $t = $this->owed();
        LandlordPayouts::sendFor($t['landlord']);

        // A fresh fake: the first one still answers the read-back with "pending", and the first matching fake wins.
        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::fake(['api.paymongo.com/v2/transfers/tr_abc123' => Http::response(['data' => ['id' => 'tr_abc123', 'status' => 'succeeded', 'provider_reference_number' => 'GC-998']], 200)]);

        $this->artisan('payouts:send')->assertSuccessful();
        $this->assertSame('Processing', Payout::sole()->status, 'Less than five minutes old: the webhook gets first go.');

        Carbon::setTestNow(now()->addMinutes(10));
        $this->artisan('payouts:send')->assertSuccessful();

        $this->assertSame('Sent', Payout::sole()->status);
        $this->assertSame('GC-998', Payout::sole()->payout_reference);
    }

    public function test_the_sweep_sends_money_whose_job_never_ran(): void
    {
        $this->fakeTransfer();
        $t = $this->owed();

        $this->artisan('payouts:send')->assertSuccessful();

        $this->assertSame('Processing', Payout::sole()->status);
        $this->assertSame(Payout::sole()->payout_id, $t['payment']->fresh()->payout_id);
    }

    // ─── when money waits ───────────────────────────────────────

    public function test_a_landlord_without_gcash_details_is_skipped(): void
    {
        Http::fake();
        $t = $this->makeTenancy();
        $this->makePayment($t['reservation'], ['payout_status' => 'Pending Payout']);

        $this->assertNull(LandlordPayouts::sendFor($t['landlord']));
        Http::assertNothingSent();
    }

    public function test_changing_the_gcash_details_holds_payouts_for_48_hours_and_tells_the_landlord(): void
    {
        Mail::fake();
        $this->fakeTransfer();
        $t = $this->owed();

        $t['landlord']->savePayoutAccount('09998887777', 'New Account');

        Mail::assertSent(GcashChangedMail::class, fn ($mail) => $mail->hasTo($t['landlord']->email));
        $this->assertTrue(Notification::where('user_id', $t['landlord']->user_id)->where('title', 'Your GCash details changed')->exists());
        $this->assertStringContainsString('GCash details changed', LandlordPayouts::blockedReason($t['landlord']));

        $this->assertNull(LandlordPayouts::sendFor($t['landlord']));
        Carbon::setTestNow(now()->addHours(47));
        $this->assertNull(LandlordPayouts::sendFor($t['landlord']));
        $this->assertCount(0, $this->sent());

        Carbon::setTestNow(now()->addHours(2));
        $this->assertNull(LandlordPayouts::sendFor($t['landlord']), 'The hold is over but the new account still needs an admin.');

        UserPayoutAccount::whereKey($t['landlord']->user_id)->update(['payout_approved_at' => now()]);
        $payout = LandlordPayouts::sendFor($t['landlord']);

        $this->assertNotNull($payout);
        $this->assertSame('09998887777', $payout->gcash_number, 'The payout goes to the new details.');
    }

    public function test_saving_the_same_details_again_holds_nothing(): void
    {
        Mail::fake();
        $t = $this->makeOnlineTenancy();

        $t['landlord']->savePayoutAccount('09171234567', 'Test Landlord');

        Mail::assertNothingSent();
        $this->assertNull(LandlordPayouts::blockedReason($t['landlord']));
    }

    public function test_setting_details_for_the_first_time_holds_only_when_money_is_already_owed(): void
    {
        Mail::fake();
        $quiet = $this->makeTenancy();
        $quiet['landlord']->savePayoutAccount('09171234567', 'Lana');
        $this->assertStringContainsString('not approved', LandlordPayouts::blockedReason($quiet['landlord']), 'No hold, but still waiting for approval.');
        $this->assertFalse(UserPayoutAccount::find($quiet['landlord']->user_id)->isOnHold());

        $owed = $this->makeTenancy();
        $this->makePayment($owed['reservation'], ['payout_status' => 'Pending Payout']);
        $owed['landlord']->savePayoutAccount('09171234567', 'Lana');
        $this->assertStringContainsString('GCash details changed', LandlordPayouts::blockedReason($owed['landlord']));
    }

    public function test_an_admin_can_pause_and_resume_a_landlord(): void
    {
        $this->fakeTransfer();
        $admin = $this->makeUserWithRole('Admin');
        $t = $this->owed();

        $this->actingAs($admin)->post(route('admin.payouts.pause', $t['landlord']))->assertRedirect();
        $this->assertNull(LandlordPayouts::sendFor($t['landlord']));
        $this->assertCount(0, $this->sent());
        $this->assertTrue(AuditLog::where('action', 'payout.pause')->exists());

        $this->actingAs($admin)->post(route('admin.payouts.pause', $t['landlord']))->assertStatus(409);
        $this->actingAs($admin)->post(route('admin.payouts.resume', $t['landlord']))->assertRedirect();

        $this->assertNotNull(LandlordPayouts::sendFor($t['landlord']));
        $this->assertTrue(AuditLog::where('action', 'payout.resume')->exists());
    }

    public function test_only_an_admin_can_pause_retry_or_check(): void
    {
        $t = $this->owed();
        $payout = LandlordPayouts::batch($t['landlord']);

        foreach ([['admin.payouts.pause', $t['landlord']], ['admin.payouts.retry', $payout], ['admin.payouts.check', $payout]] as [$route, $model]) {
            $this->actingAs($t['landlord'])->post(route($route, $model))->assertForbidden();
            $this->actingAs($t['tenant'])->post(route($route, $model))->assertForbidden();
        }
    }

    // ─── changing the destination ───────────────────────────────

    public function test_changing_gcash_on_the_web_needs_the_password(): void
    {
        Mail::fake();
        $t = $this->makeOnlineTenancy();
        $this->actingAs($t['landlord']);

        $this->patch(route('landlord.profile.update'), ['gcash_number' => '09998887777', 'gcash_account_name' => 'New Account'])
            ->assertSessionHasErrors('current_password');
        $this->assertSame('09171234567', $t['landlord']->fresh()->payoutAccount->gcash_number);

        $this->patch(route('landlord.profile.update'), ['gcash_number' => '09998887777', 'gcash_account_name' => 'New Account', 'current_password' => 'wrong'])
            ->assertSessionHasErrors('current_password');

        $this->patch(route('landlord.profile.update'), ['gcash_number' => '09998887777', 'gcash_account_name' => 'New Account', 'current_password' => 'password'])
            ->assertSessionHasNoErrors();
        $this->assertSame('09998887777', $t['landlord']->fresh()->payoutAccount->gcash_number);
        $this->assertNotNull($t['landlord']->fresh()->payoutAccount->payouts_hold_until);
        Mail::assertSent(GcashChangedMail::class);
    }

    public function test_saving_the_form_unchanged_or_for_the_first_time_needs_no_password(): void
    {
        $landlord = $this->makeLandlord();
        $this->actingAs($landlord);

        $this->patch(route('landlord.profile.update'), ['gcash_number' => '09171234567', 'gcash_account_name' => 'Lana'])->assertSessionHasNoErrors();
        $this->patch(route('landlord.profile.update'), ['gcash_number' => '09171234567', 'gcash_account_name' => 'Lana', 'business_name' => 'Lana Rentals'])->assertSessionHasNoErrors();
        $this->assertNull($landlord->fresh()->payoutAccount->payouts_hold_until, 'Nothing changed, so no hold.');
    }

    public function test_changing_gcash_over_the_api_needs_the_password_too(): void
    {
        $t = $this->makeOnlineTenancy();
        Sanctum::actingAs($t['landlord'], ['*']);
        $base = ['first_name' => $t['landlord']->first_name, 'last_name' => $t['landlord']->last_name, 'gcash_number' => '09998887777', 'gcash_account_name' => 'New Account'];

        $this->patchJson('/api/v1/landlord/profile/me', $base)->assertStatus(422)->assertJsonValidationErrors('current_password');
        $this->patchJson('/api/v1/landlord/profile/me', $base + ['current_password' => 'password'])->assertOk();

        $this->assertSame('09998887777', $t['landlord']->fresh()->payoutAccount->gcash_number);
    }

    // ─── screens ────────────────────────────────────────────────

    public function test_the_admin_page_shows_every_status_with_its_action(): void
    {
        $admin = $this->makeUserWithRole('Admin');
        $t = $this->owed();
        LandlordPayouts::batch($t['landlord']);
        Payout::create(['landlord_id' => $t['landlord']->user_id, 'amount' => 100, 'status' => 'Failed', 'failure_reason' => 'Invalid account']);
        Payout::create(['landlord_id' => $t['landlord']->user_id, 'amount' => 200, 'status' => 'Sent', 'paid_out_at' => now(), 'payout_reference' => 'tr_ok']);

        $this->actingAs($admin)->get(route('admin.payouts.index'))
            ->assertOk()
            ->assertSee('Processing')->assertSee('Failed')->assertSee('Sent')
            ->assertSee('Invalid account')->assertSee('Retry')
            ->assertSee('Retry (checked)');
    }

    public function test_the_admin_page_says_when_automatic_payouts_are_not_set_up(): void
    {
        config(['services.paymongo.gcash_bic' => null]);
        $admin = $this->makeUserWithRole('Admin');

        $this->actingAs($admin)->get(route('admin.payouts.index'))->assertOk()->assertSee('Automatic payouts are off')->assertSee('PAYMONGO_GCASH_BIC');
    }

    public function test_the_landlord_sees_sending_and_a_hold_notice(): void
    {
        Mail::fake();
        $this->fakeTransfer();
        $t = $this->owed();
        LandlordPayouts::sendFor($t['landlord']);
        $t['landlord']->savePayoutAccount('09998887777', 'New Account');

        $this->actingAs($t['landlord'])->get(route('landlord.payouts.index'))
            ->assertOk()
            ->assertSee('Sending to your GCash')
            ->assertSee('because you changed your GCash details');
    }

    public function test_marking_a_payout_by_hand_still_works_and_is_recorded_as_sent(): void
    {
        $admin = $this->makeUserWithRole('Admin');
        $t = $this->owed(2500);

        $this->actingAs($admin)->post(route('admin.payouts.markPaidOut', $t['landlord']), ['payout_reference' => 'HAND-1'])->assertRedirect();

        $payout = Payout::sole();
        $this->assertSame('Sent', $payout->status);
        $this->assertSame('HAND-1', $payout->payout_reference);
        $this->assertSame($admin->user_id, $payout->paid_out_by);
        $this->assertNotNull($payout->paid_out_at);
        $this->assertTrue(AuditLog::where('action', 'payout.mark_paid_out')->exists());
    }

    // ─── never "Sent" without PayMongo's own record ─────────────

    public function test_a_succeeded_answer_PayMongo_cannot_back_up_is_not_marked_sent(): void
    {
        // The create call says succeeded, but the transfer is not ours to read: the case that once recorded a payout as
        // Sent when nothing had left the wallet.
        Http::fake([
            self::ENDPOINT => Http::response(['data' => ['id' => 'batch_tr_1', 'transfers' => [['id' => 'tr_ghost', 'status' => 'succeeded']]]], 200),
            'api.paymongo.com/v2/transfers/tr_ghost' => Http::response(['errors' => [['code' => 'unauthorized', 'detail' => 'resource does not belong to the identity']]], 401),
        ]);
        $admin = $this->makeUserWithRole('Admin');
        $t = $this->owed();

        $payout = LandlordPayouts::sendFor($t['landlord']);

        $this->assertSame('Processing', $payout->status, 'Not Sent: PayMongo has no record the app can read.');
        $this->assertStringContainsString('Outcome unknown', (string) $payout->failure_reason);
        $this->assertSame($payout->payout_id, $t['payment']->fresh()->payout_id, 'The money stays linked, never re-sent by itself.');
        $this->assertTrue(Notification::where('user_id', $admin->user_id)->where('title', 'A payout could not be confirmed')->exists());
        Http::assertSentCount(2);
    }

    public function test_a_succeeded_answer_is_marked_sent_once_PayMongo_confirms_it(): void
    {
        $this->fakeTransfer('succeeded');
        $t = $this->owed();

        $payout = LandlordPayouts::sendFor($t['landlord']);

        $this->assertSame('Sent', $payout->status);
    }

    // ─── manual mode: the Automatic payouts switch ──────────────

    public function test_with_automatic_payouts_off_nothing_is_sent_and_nothing_is_asked_of_paymongo(): void
    {
        config(['rentals.automatic_payouts_enabled' => false]);
        Http::fake();
        $t = $this->owed();

        $this->assertStringContainsString('automatic payouts are switched off', LandlordPayouts::blockedReason($t['landlord']));
        $this->assertNull(LandlordPayouts::sendFor($t['landlord']));
        SendLandlordPayout::dispatchSync($t['landlord']->user_id);
        $this->artisan('payouts:send')->assertSuccessful();

        Http::assertNothingSent();
        $this->assertNull($t['payment']->fresh()->payout_id, 'What the landlord is owed stays owed.');
        $this->assertSame(0, Payout::count());
    }

    public function test_a_transfer_already_with_paymongo_still_finishes_in_manual_mode(): void
    {
        $this->fakeTransfer();
        $t = $this->owed();
        LandlordPayouts::sendFor($t['landlord']);
        $this->assertSame('Processing', Payout::sole()->status);

        config(['rentals.automatic_payouts_enabled' => false]);
        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::fake(['api.paymongo.com/v2/transfers/tr_abc123' => Http::response(['data' => ['id' => 'tr_abc123', 'status' => 'succeeded']], 200)]);
        Carbon::setTestNow(now()->addMinutes(10));

        $this->artisan('payouts:send')->assertSuccessful();

        $this->assertSame('Sent', Payout::sole()->status, 'Switching it off stops new sends, not ones PayMongo is finishing.');
    }

    public function test_an_admin_can_still_pay_by_hand_in_manual_mode_and_the_page_says_so(): void
    {
        config(['rentals.automatic_payouts_enabled' => false]);
        $admin = $this->makeUserWithRole('Admin');
        $t = $this->owed(2500);

        $this->actingAs($admin)->get(route('admin.payouts.index'))
            ->assertOk()->assertSee('Manual mode: automatic payouts are switched off')->assertSee('Mark Paid Out');

        $this->actingAs($admin)->post(route('admin.payouts.markPaidOut', $t['landlord']), ['payout_reference' => 'HAND-2'])->assertRedirect();

        $this->assertSame('Sent', Payout::sole()->status);
        $this->assertSame('HAND-2', Payout::sole()->payout_reference);
    }

    public function test_the_landlord_is_told_payouts_are_by_hand_for_now(): void
    {
        config(['rentals.automatic_payouts_enabled' => false]);
        $t = $this->owed();

        $this->actingAs($t['landlord'])->get(route('landlord.payouts.index'))
            ->assertOk()->assertSee('paying landlords by hand for now')->assertSee('Paid to your GCash by AbangananHub');
    }

    public function test_the_admin_switch_asks_before_turning_automatic_payouts_off(): void
    {
        $admin = $this->makeUserWithRole('Admin');
        \App\Models\Setting::put('automatic_payouts_enabled', '1');

        $html = $this->actingAs($admin)->get(route('admin.settings.edit'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/id="toggle-automatic_payouts_enabled"[^>]*data-confirm="Turn off automatic payouts/s', $html);

        $this->actingAs($admin)->patch(route('admin.settings.update'), ['settings' => ['automatic_payouts_enabled' => '0']])->assertSessionHas('success');
        $this->assertFalse(\App\Models\Setting::effective('automatic_payouts_enabled'));
    }
}
