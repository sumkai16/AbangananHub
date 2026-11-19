<?php

namespace Tests\Feature;

use App\Models\Report;
use App\Models\Reservation;
use App\Services\AdminQueues;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\Support\CreatesPaymentFixtures;
use Tests\TestCase;

/**
 * The admin Refund requests page (held-money move-in disputes and "refund not returned" escalations) and the tenant's
 * Escalate to admin button that feeds it.
 */
class RefundRequestsTest extends TestCase
{
    use CreatesMarketplaceFixtures, CreatesPaymentFixtures, DatabaseTransactions;

    /** A reservation that ended after the tenant paid ₱3,500 straight to the landlord, who recorded keeping it. */
    private function keptTenancy(): array
    {
        $t = $this->makeTenancy(['rental_status' => 'Cancelled']);
        $this->makeRecordedPayment($t['reservation'], $t['landlord'], ['payment_type' => 'Deposit', 'amount' => 3500]);
        $t['reservation']->forceFill([
            'move_in_refund_decision' => 'kept', 'move_in_refund_amount' => 0,
            'move_in_refund_note' => 'Unit was damaged', 'move_in_refund_at' => now(),
        ])->save();

        return $t;
    }

    public function test_the_page_lists_a_held_money_dispute_and_a_kept_money_escalation(): void
    {
        $dispute = $this->makeTenancy(['rental_status' => 'Rental Agreement Signed', 'move_in_disputed_at' => now()->subDays(2), 'move_in_dispute_reason' => 'Room was not as listed']);
        $this->makePayment($dispute['reservation'], ['status' => 'Held', 'amount' => 7000, 'payment_type' => 'Deposit']);

        $kept = $this->keptTenancy();
        $this->actingAs($kept['tenant'])->post(route('reservations.escalateRefund', $kept['reservation']))->assertRedirect();

        $this->actingAs($this->makeUserWithRole('Admin'))
            ->get(route('admin.refund-requests.index'))->assertOk()
            ->assertSee('Room was not as listed')->assertSee('7,000.00')
            ->assertSee('Unit was damaged')->assertSee('3,500.00')
            ->assertSee('Review report')->assertSee('Release');

        // Filter to one source.
        $this->get(route('admin.refund-requests.index', ['type' => 'escalations']))->assertOk()
            ->assertSee('Unit was damaged')->assertDontSee('Room was not as listed');
    }

    public function test_only_an_admin_can_open_the_page(): void
    {
        $this->actingAs($this->makeLandlord())->get(route('admin.refund-requests.index'))->assertForbidden();
    }

    public function test_a_tenant_can_escalate_kept_money_once(): void
    {
        $t = $this->keptTenancy();

        $this->actingAs($t['tenant'])->post(route('reservations.escalateRefund', $t['reservation']))
            ->assertRedirect()->assertSessionHas('success');

        $report = Report::where('reporter_id', $t['tenant']->user_id)->sole();
        $this->assertSame($t['landlord']->user_id, $report->reported_user_id);
        $this->assertSame('Pending', $report->report_status);
        $this->assertStringContainsString('Refund not returned', $report->report_reason);
        $this->assertStringContainsString('3,500.00', $report->report_reason);
        $this->assertSame($report->report_id, $t['reservation']->fresh()->refund_report_id);

        // A second tap changes nothing.
        $this->post(route('reservations.escalateRefund', $t['reservation']))->assertSessionHas('warning');
        $this->assertSame(1, Report::where('reporter_id', $t['tenant']->user_id)->count());
    }

    public function test_only_the_tenant_who_paid_can_escalate_and_only_when_money_was_kept(): void
    {
        $t = $this->keptTenancy();
        $this->actingAs($this->makeTenant())->post(route('reservations.escalateRefund', $t['reservation']))->assertForbidden();

        // Returned in full: nothing was kept, so nothing to escalate.
        $returned = $this->makeTenancy(['rental_status' => 'Cancelled']);
        $this->makeRecordedPayment($returned['reservation'], $returned['landlord'], ['payment_type' => 'Deposit', 'amount' => 2000]);
        $returned['reservation']->forceFill(['move_in_refund_decision' => 'refunded', 'move_in_refund_amount' => 2000, 'move_in_refund_at' => now()])->save();

        $this->actingAs($returned['tenant'])->post(route('reservations.escalateRefund', $returned['reservation']))->assertSessionHas('warning');
        $this->assertNull($returned['reservation']->fresh()->refund_report_id);
    }

    public function test_the_sidebar_number_is_the_two_lists_together_and_resolving_the_report_clears_it(): void
    {
        $before = AdminQueues::navCounts()['refunds'];
        request()->attributes->remove('admin_nav_counts');

        $this->makeTenancy(['rental_status' => 'Rental Agreement Signed', 'move_in_disputed_at' => now()]);
        $t = $this->keptTenancy();
        $this->actingAs($t['tenant'])->post(route('reservations.escalateRefund', $t['reservation']));
        request()->attributes->remove('admin_nav_counts');

        $this->assertSame($before + 2, AdminQueues::navCounts()['refunds']);

        Reservation::findOrFail($t['reservation']->reservation_id)->refundReport->resolve('Spoke to the landlord', 'none', $this->makeUserWithRole('Admin')->user_id);
        request()->attributes->remove('admin_nav_counts');

        $this->assertSame($before + 1, AdminQueues::navCounts()['refunds']);
    }

    public function test_the_report_page_explains_why_the_money_cannot_be_refunded(): void
    {
        $t = $this->keptTenancy();
        $this->actingAs($t['tenant'])->post(route('reservations.escalateRefund', $t['reservation']));
        $report = $t['reservation']->fresh()->refundReport;

        $this->actingAs($this->makeUserWithRole('Admin'))->get(route('admin.reports.show', $report))->assertOk()
            ->assertSee('Refund not returned')->assertSee('cannot refund it')->assertSee('Unit was damaged');
    }
}
