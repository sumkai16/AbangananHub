<?php

namespace Tests\Feature\Payments;

use App\Http\Resources\PaymentResource;
use App\Models\Payment;
use App\Models\Payout;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\Support\CreatesPaymentFixtures;
use Tests\TestCase;

/**
 * A payout is one GCash transfer covering several payments. Payout state is derived from
 * payments.payout_owed_at and payments.payout_id; there is no stored status.
 */
class PayoutBatchTest extends TestCase
{
    use CreatesMarketplaceFixtures, CreatesPaymentFixtures, DatabaseTransactions;

    private function owed(array $tenancy, float $amount): Payment
    {
        return $this->makePayment($tenancy['reservation'], [
            'amount' => $amount, 'billing_period' => null, 'payment_type' => 'Other', 'payment_method' => 'GCash',
            'payout_status' => 'Pending Payout',
        ]);
    }

    public function test_payout_state_is_derived_from_owed_at_and_payout_id(): void
    {
        $t = $this->makeTenancy();
        $plain = $this->makeRecordedPayment($t['reservation'], $t['landlord']);
        $pending = $this->owed($t, 1000);
        $paidOut = $this->makePayment($t['reservation'], ['payout_status' => 'Paid Out', 'billing_period' => null, 'payment_type' => 'Other']);

        $this->assertNull($plain->payout_status);
        $this->assertSame('Pending Payout', $pending->payout_status);
        $this->assertSame('Paid Out', $paidOut->payout_status);

        $this->assertSame([$pending->payment_id], Payment::pendingPayout()->pluck('payment_id')->all());
        $this->assertSame([$paidOut->payment_id], Payment::paidOut()->pluck('payment_id')->all());
    }

    public function test_the_payout_keeps_the_gcash_details_it_was_sent_to(): void
    {
        $admin = $this->makeUserWithRole('Admin');
        $t = $this->makeTenancy();
        $t['landlord']->savePayoutAccount('09171234567', 'Juan Dela Cruz');
        $this->owed($t, 2500);

        $this->actingAs($admin)
            ->post(route('admin.payouts.markPaidOut', $t['landlord']), ['payout_reference' => 'REF-1', 'expected_total' => 2500])
            ->assertRedirect();

        $t['landlord']->savePayoutAccount('09990000000', 'Someone Else');

        $payout = Payout::sole();
        $this->assertSame('09171234567', $payout->gcash_number);
        $this->assertSame('Juan Dela Cruz', $payout->gcash_account_name);
        $this->assertSame('2500.00', $payout->amount);
        $this->assertSame($admin->user_id, $payout->paid_out_by);
    }

    public function test_a_payment_owed_to_the_landlord_can_no_longer_be_voided(): void
    {
        $t = $this->makeTenancy();
        $recorded = $this->makeRecordedPayment($t['reservation'], $t['landlord']);
        $this->assertTrue($recorded->canBeVoided());

        $recorded->update(['payout_owed_at' => now()]);

        $this->assertFalse($recorded->fresh()->canBeVoided());
    }

    public function test_the_mobile_payload_keeps_its_payout_keys(): void
    {
        $t = $this->makeTenancy();
        $paid = $this->makePayment($t['reservation'], ['payout_status' => 'Paid Out', 'billing_period' => null, 'payment_type' => 'Other']);

        $this->actingAs($t['landlord']);

        $payload = (new PaymentResource($paid->fresh()))->resolve();

        $this->assertSame('Paid Out', $payload['payout_status']);
        $this->assertSame('TEST-PAYOUT', $payload['payout_reference']);
        $this->assertNotNull($payload['paid_out_at']);
        $this->assertSame($paid->payout_id, $payload['payout_id']);
    }

    public function test_an_unpaid_payment_serialises_null_payout_fields_without_a_query(): void
    {
        $t = $this->makeTenancy();
        $payment = $this->owed($t, 900);

        $this->actingAs($t['landlord']);

        $payload = (new PaymentResource($payment->fresh()))->resolve();

        $this->assertSame('Pending Payout', $payload['payout_status']);
        $this->assertNull($payload['paid_out_at']);
        $this->assertNull($payload['payout_reference']);
    }
}
