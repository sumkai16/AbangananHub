<?php

namespace Tests\Feature\Payments;

use App\Models\DepositCharge;
use App\Support\BusinessTime;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\Support\CreatesPaymentFixtures;
use Tests\TestCase;

/** A landlord can claim against a held deposit, but only their own tenancy's, and never more than is held. */
class DepositChargeTest extends TestCase
{
    use CreatesMarketplaceFixtures, CreatesPaymentFixtures, DatabaseTransactions;

    private function tenancyWithDeposit(float $deposit = 5000): array
    {
        $t = $this->makeTenancy();
        $this->makePayment($t['reservation'], ['payment_type' => 'Deposit', 'billing_period' => null, 'amount' => $deposit, 'status' => 'Held', 'payment_method' => 'GCash']);

        return $t;
    }

    private function charge(array $t, array $data = [], $as = null)
    {
        return $this->actingAs($as ?? $t['landlord'])->post(route('landlord.depositCharges.store', $t['reservation']), array_merge([
            'amount'      => 1500,
            'category'    => 'Damage',
            'description' => 'Broken door',
        ], $data));
    }

    public function test_a_landlord_can_charge_part_of_the_held_deposit(): void
    {
        $t = $this->tenancyWithDeposit();

        $this->charge($t)->assertRedirect();

        $this->assertSame(1, DepositCharge::where('reservation_id', $t['reservation']->reservation_id)->count());
    }

    public function test_charges_can_never_add_up_to_more_than_the_deposit(): void
    {
        $t = $this->tenancyWithDeposit(5000);

        $this->charge($t, ['amount' => 3000])->assertRedirect();
        $this->charge($t, ['amount' => 2500])->assertStatus(422);   // only 2,000 left
        $this->charge($t, ['amount' => 2000])->assertRedirect();    // exactly what is left is fine

        $this->assertEquals(5000, DepositCharge::where('reservation_id', $t['reservation']->reservation_id)->sum('amount'));
    }

    public function test_nothing_can_be_charged_when_no_deposit_is_held(): void
    {
        $t = $this->makeTenancy();

        $this->charge($t)->assertStatus(422);

        $this->assertSame(0, DepositCharge::count());
    }

    public function test_another_landlord_and_the_tenant_cannot_charge_the_deposit(): void
    {
        $t = $this->tenancyWithDeposit();

        $this->charge($t, [], $this->makeLandlord())->assertForbidden();
        $this->charge($t, [], $t['tenant'])->assertForbidden();

        $this->assertSame(0, DepositCharge::count());
    }

    public function test_a_charge_needs_a_real_amount_category_and_description(): void
    {
        $t = $this->tenancyWithDeposit();

        $this->charge($t, ['amount' => 0])->assertSessionHasErrors('amount');
        $this->charge($t, ['category' => 'Nonsense'])->assertSessionHasErrors('category');
        $this->charge($t, ['description' => ''])->assertSessionHasErrors('description');
        $this->charge($t, ['charged_at' => BusinessTime::now()->addDay()->toDateString()])->assertSessionHasErrors('charged_at');
    }

    public function test_a_voided_charge_frees_that_part_of_the_deposit_again(): void
    {
        $t = $this->tenancyWithDeposit(5000);
        $this->charge($t, ['amount' => 4000])->assertRedirect();
        $charge = DepositCharge::firstOrFail();

        $this->actingAs($t['landlord'])
            ->post(route('landlord.depositCharges.void', $charge), ['void_reason' => 'wrong_amount'])
            ->assertRedirect();

        $this->assertTrue($charge->fresh()->isVoided());
        $this->charge($t, ['amount' => 4500])->assertRedirect();
    }

    public function test_the_tenant_cannot_void_a_charge_against_their_own_deposit(): void
    {
        $t = $this->tenancyWithDeposit();
        $this->charge($t)->assertRedirect();
        $charge = DepositCharge::firstOrFail();

        $this->actingAs($t['tenant'])
            ->post(route('landlord.depositCharges.void', $charge), ['void_reason' => 'wrong_amount'])
            ->assertForbidden();

        $this->assertFalse($charge->fresh()->isVoided());
    }
}
