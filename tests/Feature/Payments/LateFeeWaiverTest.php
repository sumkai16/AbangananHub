<?php

namespace Tests\Feature\Payments;

use App\Models\AuditLog;
use App\Models\LateFeeWaiver;
use App\Models\Notification;
use App\Services\RentLedger;
use App\Services\RentReminderNotifier;
use App\Support\LeaseTerms;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\Support\CreatesPaymentFixtures;
use Tests\TestCase;

class LateFeeWaiverTest extends TestCase
{
    use CreatesMarketplaceFixtures, CreatesPaymentFixtures, DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        config(['rentals.rent_overdue_grace_days' => 0]);
        $this->travelTo(now()->setDate(2026, 10, 20)->startOfDay());
    }

    private function feeTenancy(): array
    {
        $t = $this->makeTenancy();
        // A real snapshot (the tenancy pages render every key), with the rules under test set on it.
        $snapshot = ['grace_days' => 2, 'late_fee' => ['type' => 'fixed', 'value' => 100]] + LeaseTerms::snapshot($t['reservation']->fresh());
        $t['reservation']->forceFill(['lease_snapshot' => $snapshot])->save();

        return $t;
    }

    public function test_the_landlord_waives_one_months_fee_and_it_is_audited(): void
    {
        $t = $this->feeTenancy();

        $this->actingAs($t['landlord'])
            ->post(route('landlord.tenancies.waiveLateFee', $t['reservation']), ['billing_period' => '2026-09-01', 'reason' => 'Paid by cash on the 10th'])
            ->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseHas('late_fee_waivers', ['reservation_id' => $t['reservation']->reservation_id, 'billing_period' => '2026-09-01', 'waived_by' => $t['landlord']->user_id]);
        $this->assertSame(1, AuditLog::where('action', 'late_fee.waive')->count());

        $periods = RentLedger::for($t['reservation']->fresh())->periods();
        $this->assertSame(0.0, $periods->first(fn ($p) => $p['period']->format('Y-m') === '2026-09')['late_fee']);
        $this->assertSame(100.0, $periods->first(fn ($p) => $p['period']->format('Y-m') === '2026-10')['late_fee']);
    }

    public function test_waiving_twice_is_harmless(): void
    {
        $t = $this->feeTenancy();
        $url = route('landlord.tenancies.waiveLateFee', $t['reservation']);

        $this->actingAs($t['landlord'])->post($url, ['billing_period' => '2026-09-01']);
        $this->actingAs($t['landlord'])->post($url, ['billing_period' => '2026-09-01'])->assertSessionHas('success');

        $this->assertSame(1, LateFeeWaiver::count());
        $this->assertSame(1, AuditLog::where('action', 'late_fee.waive')->count());
    }

    public function test_a_month_with_no_fee_cannot_be_waived_ahead_of_time(): void
    {
        $t = $this->feeTenancy();

        $this->actingAs($t['landlord'])
            ->post(route('landlord.tenancies.waiveLateFee', $t['reservation']), ['billing_period' => '2027-03-01'])
            ->assertSessionHas('warning');

        $this->assertSame(0, LateFeeWaiver::count());
    }

    public function test_only_the_tenancys_landlord_can_waive(): void
    {
        $t = $this->feeTenancy();
        $other = $this->makeLandlord();
        $url = route('landlord.tenancies.waiveLateFee', $t['reservation']);

        $this->actingAs($other)->post($url, ['billing_period' => '2026-09-01'])->assertForbidden();
        $this->actingAs($t['tenant'])->post($url, ['billing_period' => '2026-09-01'])->assertForbidden();
        $this->assertSame(0, LateFeeWaiver::count());
    }

    public function test_the_landlord_ledger_offers_the_waive_button_and_the_tenant_does_not(): void
    {
        $t = $this->feeTenancy();

        // The row payload is Js::from() JSON in an attribute; the key only appears there when the landlord may waive.
        $waiveKey = 'waive_action\\u0022';

        $this->actingAs($t['landlord'])->get(route('landlord.payments.ledger', $t['reservation']))
            ->assertOk()->assertSee('incl. ₱100.00 late fee')->assertSee($waiveKey, false);
        $this->actingAs($t['tenant'])->get(route('tenancy.show', $t['reservation']))
            ->assertOk()->assertSee('incl. ₱100.00 late fee')->assertDontSee($waiveKey, false);
    }

    public function test_a_reminder_mentions_the_late_fee(): void
    {
        $t = $this->feeTenancy();
        $period = RentLedger::for($t['reservation']->fresh())->periods()->first();

        app(RentReminderNotifier::class)->sendToTenant($t['reservation']->fresh(['tenant', 'property']), $period, Carbon::today());

        $this->assertStringContainsString('including a ₱100.00 late fee', Notification::where('user_id', $t['tenant']->user_id)->latest('notification_id')->first()->message);
    }

    public function test_the_lease_prints_the_agreed_grace_and_fee_and_a_legacy_lease_prints_none(): void
    {
        $t = $this->makeTenancy();
        $snapshot = LeaseTerms::snapshot($t['reservation']->fresh());
        $snapshot['grace_days'] = 3;
        $snapshot['late_fee'] = ['type' => 'percent', 'value' => 10.0];

        $html = view('leases._document', ['terms' => $snapshot])->render();
        $this->assertStringContainsString('within 3 days after the due date', $html);
        $this->assertStringContainsString('late fee of', $html);
        $this->assertStringContainsString('10% of the monthly rent', $html);

        $legacy = view('leases._document', ['terms' => LeaseTerms::for($t['reservation']->fresh())])->render();
        $this->assertStringNotContainsString('late fee', $legacy);
    }
}
