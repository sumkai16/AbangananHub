<?php

namespace Tests\Feature;

use App\Models\DepositCharge;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\Support\CreatesPaymentFixtures;
use Tests\TestCase;

/** The Payments and Deposit Charges lists share the Reservations filter bar: search, a status dropdown, sort. */
class AdminFilterBarsPaymentsTest extends TestCase
{
    use CreatesMarketplaceFixtures, CreatesPaymentFixtures, DatabaseTransactions;

    public function test_payments_search_by_tenant_and_landlord_and_counts_follow_it(): void
    {
        $t = $this->makeTenancy(['rental_status' => 'Occupied']);
        $this->makePayment($t['reservation'], ['status' => 'Held', 'amount' => 5000]);
        $admin = $this->makeUserWithRole('Admin');
        $title = $t['reservation']->property->title;

        $this->actingAs($admin)->get(route('admin.payments.index', ['search' => $t['tenant']->first_name]))->assertOk()->assertSee($title);
        $this->actingAs($admin)->get(route('admin.payments.index', ['search' => $t['landlord']->last_name, 'sort' => 'amount_desc']))->assertOk()->assertSee($title);
        $this->actingAs($admin)->get(route('admin.payments.index', ['search' => 'zzz-no-such-person']))->assertOk()->assertDontSee($title)->assertSee('Held (0)');
        $this->actingAs($admin)->get(route('admin.payments.index', ['status' => 'Held']))->assertOk()->assertSee($title);
    }

    public function test_deposit_charges_search_and_status_dropdown(): void
    {
        $t = $this->makeTenancy(['rental_status' => 'Occupied']);
        DepositCharge::create(['reservation_id' => $t['reservation']->reservation_id, 'amount' => 1500, 'category' => 'Damage', 'description' => 'Door', 'charged_at' => today(), 'charged_by' => $t['landlord']->user_id]);
        $admin = $this->makeUserWithRole('Admin');
        $title = $t['reservation']->property->title;

        $this->actingAs($admin)->get(route('admin.deposit-charges.index', ['search' => $t['tenant']->email]))->assertOk()->assertSee($title)->assertSee('Active (1)');
        $this->actingAs($admin)->get(route('admin.deposit-charges.index', ['search' => 'zzz-no-such-person', 'sort' => 'oldest']))->assertOk()->assertDontSee($title);
        $this->actingAs($admin)->get(route('admin.deposit-charges.index', ['status' => 'Voided']))->assertOk()->assertDontSee($title);
    }
}
