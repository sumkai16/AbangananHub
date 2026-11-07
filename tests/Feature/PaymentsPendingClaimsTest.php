<?php

namespace Tests\Feature;

use App\Models\PaymentClaim;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\Support\CreatesPaymentFixtures;
use Tests\TestCase;

/** Payments > "Payments to confirm", the summary strip being list-only, and that the calendar still shows them (the zoomable calendar has its own tests: PaymentsCalendarZoomTest). */
class PaymentsPendingClaimsTest extends TestCase
{
    use CreatesMarketplaceFixtures, CreatesPaymentFixtures, DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        config(['rentals.online_payments_enabled' => false]);
        Carbon::setTestNow('2026-10-14 10:00:00');
    }

    private function claim(array $t, string $method = 'Cash'): PaymentClaim
    {
        return $t['reservation']->paymentClaims()->create([
            'claim_type' => 'Monthly', 'billing_period' => '2026-08-01', 'amount' => 5000, 'payment_method' => $method,
            'reference_no' => $method === 'Cash' ? null : 'REF-'.uniqid(), 'paid_on' => '2026-10-13', 'status' => 'Pending',
            'filed_by' => $t['tenant']->user_id,
        ]);
    }

    public function test_every_waiting_report_is_listed_first_with_who_and_where(): void
    {
        $a = $this->makeTenancy();
        $b = $this->makeTenancy([], $a['landlord']);
        $a['tenant']->update(['first_name' => 'Ana']);
        $b['tenant']->update(['first_name' => 'Ben']);
        $this->claim($a);
        $this->claim($b, 'GCash');

        $html = $this->actingAs($a['landlord'])->get(route('landlord.payments.index'))->assertOk()->getContent();

        $this->assertStringContainsString('Payments to confirm', $html);
        $this->assertStringContainsString('Ana', $html);
        $this->assertStringContainsString('Ben', $html);
        $this->assertLessThan(strpos($html, 'Collected this month'), strpos($html, 'Payments to confirm'), 'Reports sit above every figure.');
    }

    public function test_another_landlords_reports_are_not_shown(): void
    {
        $mine = $this->makeTenancy();
        $other = $this->makeTenancy();
        $other['tenant']->update(['first_name' => 'Stranger']);
        $this->claim($other);

        $this->actingAs($mine['landlord'])->get(route('landlord.payments.index'))->assertOk()->assertDontSee('Stranger')->assertDontSee('Payments to confirm');
    }

    public function test_the_reports_also_show_in_calendar_view_and_confirming_returns_to_the_page(): void
    {
        $t = $this->makeTenancy();
        $claim = $this->claim($t);
        $page = route('landlord.payments.index', ['view' => 'calendar']);

        $this->actingAs($t['landlord'])->get($page)->assertOk()->assertSee('Payments to confirm');

        $this->actingAs($t['landlord'])->from($page)->post(route('landlord.paymentClaims.confirm', $claim))->assertRedirect($page);
        $this->assertSame('Confirmed', $claim->fresh()->status);
    }

    public function test_the_summary_strip_is_for_the_list_view_only(): void
    {
        $t = $this->makeTenancy();

        $this->actingAs($t['landlord'])->get(route('landlord.payments.index'))->assertOk()->assertSee('Collected this month')->assertSee('Still owed');
        $this->actingAs($t['landlord'])->get(route('landlord.payments.index', ['view' => 'calendar']))->assertOk()
            ->assertDontSee('Collected this month')->assertDontSee('Still owed');
    }
}
