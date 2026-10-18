<?php

namespace Tests\Feature\Payments;

use App\Models\Payment;
use App\Models\Payout;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\Support\CreatesPaymentFixtures;
use Tests\TestCase;

/**
 * The admin sends one lump GCash transfer for the total they were shown. If a payment was released while they
 * were sending it, recording the payout would mark money paid out that was never sent, so the total posted
 * from the page is pinned and compared under the lock.
 */
class PayoutPinTest extends TestCase
{
    use CreatesMarketplaceFixtures, CreatesPaymentFixtures, DatabaseTransactions;

    private function owed(array $t, float $amount): Payment
    {
        return $this->makePayment($t['reservation'], [
            'amount' => $amount, 'billing_period' => null, 'payment_type' => 'Other', 'payment_method' => 'GCash',
            'payout_status' => 'Pending Payout',
        ]);
    }

    public function test_a_total_that_changed_since_the_page_loaded_is_refused_and_nothing_is_paid_out(): void
    {
        $admin = $this->makeUserWithRole('Admin');
        $t = $this->makeTenancy();
        $first = $this->owed($t, 2500);
        $shown = '2500.00';
        $late = $this->owed($t, 1000); // released while the admin was sending the transfer

        $this->actingAs($admin)
            ->post(route('admin.payouts.markPaidOut', $t['landlord']), ['payout_reference' => 'REF-1', 'expected_total' => $shown])
            ->assertStatus(409);

        $this->assertNull($first->fresh()->payout_id);
        $this->assertNull($late->fresh()->payout_id);
        $this->assertSame(0, Payout::where('landlord_id', $t['landlord']->user_id)->count());
    }

    public function test_a_matching_total_is_recorded(): void
    {
        $admin = $this->makeUserWithRole('Admin');
        $t = $this->makeTenancy();
        $a = $this->owed($t, 2500);
        $b = $this->owed($t, 1000);

        $this->actingAs($admin)
            ->post(route('admin.payouts.markPaidOut', $t['landlord']), ['payout_reference' => 'REF-2', 'expected_total' => '3500.00'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertNotNull($a->fresh()->payout_id);
        $this->assertSame($a->fresh()->payout_id, $b->fresh()->payout_id);
    }

    public function test_a_total_made_of_fractional_amounts_still_matches(): void
    {
        $admin = $this->makeUserWithRole('Admin');
        $t = $this->makeTenancy();
        $payments = collect([1, 2, 3])->map(fn () => $this->owed($t, 1500.10));

        $this->actingAs($admin)
            ->post(route('admin.payouts.markPaidOut', $t['landlord']), ['payout_reference' => 'REF-3', 'expected_total' => '4500.30'])
            ->assertRedirect();

        $payments->each(fn (Payment $p) => $this->assertNotNull($p->fresh()->payout_id));
    }

    public function test_the_modal_form_carries_the_raw_total_it_displayed(): void
    {
        $admin = $this->makeUserWithRole('Admin');
        $t = $this->makeTenancy();
        $t['landlord']->savePayoutAccount('09171234567', 'Juan Dela Cruz');
        $this->owed($t, 12345.6);

        $html = $this->actingAs($admin)->get(route('admin.payouts.index'))->assertOk()->getContent();

        $this->assertStringContainsString('name="expected_total"', $html);
        $this->assertStringContainsString("rawAmount: '12345.60'", $html);
    }
}
