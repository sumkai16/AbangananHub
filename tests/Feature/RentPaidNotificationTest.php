<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\Payment;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\Support\CreatesPaymentFixtures;
use Tests\TestCase;

class RentPaidNotificationTest extends TestCase
{
    use CreatesMarketplaceFixtures, CreatesPaymentFixtures, DatabaseTransactions;

    private function pendingRent(array $t): Payment
    {
        return $this->makePayment($t['reservation'], [
            'status' => 'Pending', 'paid_at' => null, 'payment_method' => 'GCash',
            'paymongo_checkout_session_id' => 'cs_rent_1',
        ]);
    }

    public function test_online_rent_notifies_the_landlord_and_the_tenant(): void
    {
        $t = $this->makeTenancy();
        $payment = $this->pendingRent($t);

        $payment->update(['status' => 'Paid', 'paid_at' => now()]);

        $landlordNote = Notification::where('user_id', $t['landlord']->user_id)->where('title', 'Rent received')->sole();
        $this->assertStringContainsString('Aug 2026', $landlordNote->message);
        $this->assertSame(route('landlord.tenancies.show', $t['reservation']), $landlordNote->link);

        $tenantNote = Notification::where('user_id', $t['tenant']->user_id)->where('title', 'Rent payment received')->sole();
        $this->assertSame(route('tenancy.show', $t['reservation']), $tenantNote->link);
    }

    public function test_a_payment_the_landlord_recorded_sends_nothing_from_here(): void
    {
        $t = $this->makeTenancy();
        $this->makeRecordedPayment($t['reservation'], $t['landlord']);

        $this->assertSame(0, Notification::where('title', 'Rent received')->count());
        $this->assertSame(0, Notification::where('title', 'Rent payment received')->count());
    }

    public function test_a_recorded_payment_that_changes_status_is_still_skipped(): void
    {
        $t = $this->makeTenancy();
        $payment = $this->makeRecordedPayment($t['reservation'], $t['landlord'], ['status' => 'Pending', 'paid_at' => null]);

        $payment->update(['status' => 'Paid']);

        $this->assertSame(0, Notification::where('title', 'Rent received')->count());
    }

    public function test_the_initial_payment_does_not_use_the_rent_notice(): void
    {
        $t = $this->makeTenancy();
        $payment = $this->makePendingCheckout($t['reservation'], 'cs_init_1');

        $payment->update(['status' => 'Held']);

        $this->assertSame(0, Notification::where('title', 'Rent received')->count());
    }
}
