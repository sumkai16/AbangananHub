<?php

namespace Tests\Feature\Payments;

use App\Models\AuditLog;
use App\Models\Payment;
use App\Models\Payout;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\Support\CreatesPaymentFixtures;
use Tests\TestCase;

/**
 * Who may void, release or pay out money, and what state each payment has to be
 * in first. These are the routes where a missing check costs real pesos.
 */
class PaymentAccessAndStateTest extends TestCase
{
    use CreatesMarketplaceFixtures, CreatesPaymentFixtures, DatabaseTransactions;

    // ─── Landlord: void a payment they recorded ─────────────────

    public function test_a_landlord_can_void_a_payment_they_recorded_and_it_is_logged(): void
    {
        $t = $this->makeTenancy();
        $payment = $this->makeRecordedPayment($t['reservation'], $t['landlord']);

        $this->actingAs($t['landlord'])
            ->post(route('landlord.payments.void', $payment), ['void_reason' => 'wrong_amount'])
            ->assertRedirect();

        $payment->refresh();
        $this->assertSame('Voided', $payment->status);
        $this->assertSame('wrong_amount', $payment->void_reason);
        $this->assertSame($t['landlord']->user_id, $payment->voided_by);
        $this->assertNotNull($payment->voided_at);
        $this->assertTrue(AuditLog::where('action', 'payment.void')->exists());
    }

    public function test_a_voided_payment_stays_on_record_and_cannot_be_voided_again(): void
    {
        $t = $this->makeTenancy();
        $payment = $this->makeRecordedPayment($t['reservation'], $t['landlord']);

        $this->actingAs($t['landlord'])->post(route('landlord.payments.void', $payment), ['void_reason' => 'duplicate']);
        $this->actingAs($t['landlord'])
            ->post(route('landlord.payments.void', $payment), ['void_reason' => 'duplicate'])
            ->assertStatus(409);

        $this->assertSame(1, AuditLog::where('action', 'payment.void')->count());
        $this->assertNotNull(Payment::find($payment->payment_id), 'Financial rows are never deleted.');
    }

    public function test_another_landlord_cannot_void_a_payment_on_someone_elses_tenancy(): void
    {
        $t = $this->makeTenancy();
        $payment = $this->makeRecordedPayment($t['reservation'], $t['landlord']);

        $this->actingAs($this->makeLandlord())
            ->post(route('landlord.payments.void', $payment), ['void_reason' => 'wrong_amount'])
            ->assertForbidden();

        $this->assertSame('Paid', $payment->fresh()->status);
    }

    public function test_a_tenant_cannot_void_a_payment(): void
    {
        $t = $this->makeTenancy();
        $payment = $this->makeRecordedPayment($t['reservation'], $t['landlord']);

        $this->actingAs($t['tenant'])
            ->post(route('landlord.payments.void', $payment), ['void_reason' => 'wrong_amount'])
            ->assertForbidden();

        $this->assertSame('Paid', $payment->fresh()->status);
    }

    public function test_a_payment_settled_through_paymongo_cannot_be_voided_by_the_landlord(): void
    {
        $t = $this->makeTenancy();
        $held = $this->makePayment($t['reservation'], ['payment_type' => 'Initial', 'billing_period' => null, 'status' => 'Held', 'payment_method' => 'GCash']);

        $this->actingAs($t['landlord'])
            ->post(route('landlord.payments.void', $held), ['void_reason' => 'wrong_amount'])
            ->assertForbidden();

        $this->assertSame('Held', $held->fresh()->status);
    }

    public function test_voiding_needs_a_reason_and_a_note_when_the_reason_is_other(): void
    {
        $t = $this->makeTenancy();
        $payment = $this->makeRecordedPayment($t['reservation'], $t['landlord']);

        $this->actingAs($t['landlord'])->post(route('landlord.payments.void', $payment), [])
            ->assertSessionHasErrors('void_reason');
        $this->actingAs($t['landlord'])->post(route('landlord.payments.void', $payment), ['void_reason' => 'other'])
            ->assertSessionHasErrors('void_note');

        $this->assertSame('Paid', $payment->fresh()->status);
    }

    // ─── Admin: release escrow ──────────────────────────────────

    public function test_an_admin_release_moves_held_money_to_pending_payout(): void
    {
        $t = $this->makeTenancy();
        $admin = $this->makeUserWithRole('Admin');
        $held = $this->makePayment($t['reservation'], ['payment_type' => 'Initial', 'billing_period' => null, 'status' => 'Held', 'payment_method' => 'GCash']);

        $this->actingAs($admin)->post(route('admin.payments.release', $held))->assertRedirect();

        $held->refresh();
        $this->assertSame('Released', $held->status);
        $this->assertSame('Pending Payout', $held->payout_status);
        $this->assertSame('admin_manual', $held->release_reason);
        $this->assertSame($admin->user_id, $held->released_by);
    }

    public function test_only_held_payments_can_be_released(): void
    {
        $t = $this->makeTenancy();
        $admin = $this->makeUserWithRole('Admin');

        foreach (['Pending', 'Paid', 'Released', 'Voided'] as $status) {
            $payment = $this->makePayment($t['reservation'], ['status' => $status, 'billing_period' => null, 'payment_type' => 'Other']);

            $this->actingAs($admin)->post(route('admin.payments.release', $payment))->assertStatus(409);

            $this->assertSame($status, $payment->fresh()->status, "A {$status} payment must not change on release.");
            $this->assertNull($payment->fresh()->payout_status);
        }
    }

    public function test_landlords_and_tenants_cannot_release_escrow(): void
    {
        $t = $this->makeTenancy();
        $held = $this->makePayment($t['reservation'], ['payment_type' => 'Initial', 'billing_period' => null, 'status' => 'Held', 'payment_method' => 'GCash']);

        $this->actingAs($t['landlord'])->post(route('admin.payments.release', $held))->assertForbidden();
        $this->actingAs($t['tenant'])->post(route('admin.payments.release', $held))->assertForbidden();

        $this->assertSame('Held', $held->fresh()->status);
    }

    // ─── Admin: mark a payout as sent ───────────────────────────

    private function pendingPayout(array $tenancy, float $amount): Payment
    {
        return $this->makePayment($tenancy['reservation'], [
            'amount'         => $amount,
            'status'         => 'Paid',
            'payout_status'  => 'Pending Payout', // translated by makePayment() into payout_owed_at
            'billing_period' => null,
            'payment_type'   => 'Other',
            'payment_method' => 'GCash',
        ]);
    }

    public function test_marking_a_payout_sent_covers_only_that_landlords_pending_payments(): void
    {
        $admin = $this->makeUserWithRole('Admin');
        $mine = $this->makeTenancy();
        $theirs = $this->makeTenancy();
        $a = $this->pendingPayout($mine, 5000);
        $b = $this->pendingPayout($mine, 3000);
        $other = $this->pendingPayout($theirs, 7000);

        $this->actingAs($admin)
            ->post(route('admin.payouts.markPaidOut', $mine['landlord']), ['payout_reference' => 'GCASH-REF-1'])
            ->assertRedirect();

        foreach ([$a, $b] as $payment) {
            $payment->refresh();
            $this->assertSame('Paid Out', $payment->payout_status);
            $this->assertSame('GCASH-REF-1', $payment->payout->payout_reference);
            $this->assertSame($admin->user_id, $payment->payout->paid_out_by);
        }

        $this->assertSame($a->fresh()->payout_id, $b->fresh()->payout_id, 'One transfer covers the whole batch.');
        $this->assertSame(1, Payout::where('landlord_id', $mine['landlord']->user_id)->count());
        $this->assertSame('8000.00', Payout::where('landlord_id', $mine['landlord']->user_id)->value('amount'));

        $this->assertSame('Pending Payout', $other->fresh()->payout_status, 'Another landlord\'s payout must be untouched.');
        $this->assertTrue(AuditLog::where('action', 'payout.mark_paid_out')->exists());
    }

    public function test_a_payout_needs_a_reference_and_something_pending(): void
    {
        $admin = $this->makeUserWithRole('Admin');
        $t = $this->makeTenancy();
        $payment = $this->pendingPayout($t, 5000);

        $this->actingAs($admin)->post(route('admin.payouts.markPaidOut', $t['landlord']), [])
            ->assertSessionHasErrors('payout_reference');
        $this->assertSame('Pending Payout', $payment->fresh()->payout_status);

        $this->actingAs($admin)->post(route('admin.payouts.markPaidOut', $t['landlord']), ['payout_reference' => 'R1'])->assertRedirect();
        $this->actingAs($admin)->post(route('admin.payouts.markPaidOut', $t['landlord']), ['payout_reference' => 'R2'])->assertStatus(409);

        $this->assertSame('R1', $payment->fresh()->payout->payout_reference, 'A second click must not overwrite the reference.');
        $this->assertSame(1, Payout::count(), 'A second click must not create a second payout.');
    }

    public function test_a_landlord_cannot_mark_their_own_payout_as_sent(): void
    {
        $t = $this->makeTenancy();
        $payment = $this->pendingPayout($t, 5000);

        $this->actingAs($t['landlord'])
            ->post(route('admin.payouts.markPaidOut', $t['landlord']), ['payout_reference' => 'SELF'])
            ->assertForbidden();

        $this->assertSame('Pending Payout', $payment->fresh()->payout_status);
    }
}
