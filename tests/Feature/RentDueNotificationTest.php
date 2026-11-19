<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\PaymentClaim;
use App\Models\Reservation;
use App\Services\RentReminderNotifier;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\Support\CreatesPaymentFixtures;
use Tests\TestCase;

/** The landlord's "Needs action" notices: tenants who owe rent and payment reports waiting for an answer. */
class RentDueNotificationTest extends TestCase
{
    use CreatesMarketplaceFixtures, CreatesPaymentFixtures, DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-14 10:00:00');
        config(['rentals.online_payments_enabled' => false]);
    }

    /** Rent 5,000 due on the 5th since August: August, September and October are owed. */
    private function owing(): array
    {
        $t = $this->makeTenancy();
        $t['tenant']->update(['first_name' => 'Ana', 'last_name' => 'Reyes']);

        return $t;
    }

    private function remind(array $t): ?Notification
    {
        $ledger = \App\Services\RentLedger::for($t['reservation']->fresh());
        app(RentReminderNotifier::class)->sendToLandlord($t['reservation']->fresh(), $ledger->unsettledPeriods()->first(), Carbon::now());

        return Notification::where('user_id', $t['landlord']->user_id)->latest('notification_id')->first();
    }

    public function test_the_notice_names_the_tenant_the_total_and_the_months(): void
    {
        $t = $this->owing();

        $notice = $this->remind($t);

        $this->assertSame('rent_due', $notice->type);
        $this->assertStringContainsString('Ana Reyes owes ₱15,000.00 for 3 months', $notice->message);
        $this->assertStringContainsString($t['reservation']->unit->unit_label, $notice->message);
        $this->assertSame(Reservation::class, $notice->notifiable_type);
        $this->assertSame($t['reservation']->reservation_id, (int) $notice->notifiable_id);
        $this->assertStringContainsString(route('landlord.payments.ledger', $t['reservation']), $notice->link);
    }

    public function test_one_owed_month_keeps_the_plain_due_date_sentence(): void
    {
        $t = $this->owing();
        $this->makePayment($t['reservation'], ['billing_period' => '2026-08-01']);
        $this->makePayment($t['reservation'], ['billing_period' => '2026-09-01']);

        $notice = $this->remind($t);

        $this->assertStringContainsString("Ana Reyes's Oct 2026 rent (₱5,000.00)", $notice->message);
        $this->assertStringNotContainsString(' owes ', $notice->message);
    }

    public function test_the_needs_action_tab_lists_rent_notices_and_payment_reports_only(): void
    {
        $t = $this->owing();
        $this->remind($t);
        Notification::notify($t['landlord']->user_id, 'payment_to_confirm', 'Payment to confirm', 'Ana says she paid.', '/x');
        Notification::notify($t['landlord']->user_id, 'review', 'New review', 'Five stars.', '/y');

        $this->actingAs($t['landlord'])->get(route('notifications.index', ['tab' => 'needs_action']))
            ->assertOk()
            ->assertSee('Rent overdue')
            ->assertSee('Payment to confirm')
            ->assertDontSee('New review');

        $this->actingAs($t['landlord'])->get(route('notifications.index'))->assertOk()->assertSee('Needs action');
    }

    public function test_a_tenant_does_not_get_the_needs_action_tab(): void
    {
        $t = $this->owing();

        $this->actingAs($t['tenant'])->get(route('notifications.index'))->assertOk()->assertDontSee('Needs action');
    }

    public function test_paying_everything_marks_the_notice_read_but_a_part_payment_does_not(): void
    {
        $t = $this->owing();
        $notice = $this->remind($t);
        $this->assertFalse($notice->fresh()->is_read);

        $this->makePayment($t['reservation'], ['billing_period' => '2026-08-01']);
        $this->assertFalse($notice->fresh()->is_read, 'Two months are still owed.');

        $this->makePayment($t['reservation'], ['billing_period' => '2026-09-01']);
        $this->makePayment($t['reservation'], ['billing_period' => '2026-10-01', 'amount' => 2500]);
        $this->assertFalse($notice->fresh()->is_read, 'October is only half paid.');

        $this->makePayment($t['reservation'], ['billing_period' => '2026-10-01', 'amount' => 2500]);
        $this->assertTrue($notice->fresh()->is_read);
    }

    public function test_a_payment_report_notice_is_typed_and_marked_read_once_it_is_answered(): void
    {
        $t = $this->owing();
        $this->actingAs($t['tenant'])->post(route('paymentClaims.store', $t['reservation']), [
            'accept_terms' => '1', 'claim_type' => 'Monthly', 'billing_period' => '2026-08-01', 'amount' => 5000,
            'payment_method' => 'GCash', 'reference_no' => 'RENT-1', 'paid_on' => '2026-10-13',
        ])->assertSessionHasNoErrors();

        $claim = PaymentClaim::firstOrFail();
        $notice = Notification::where('user_id', $t['landlord']->user_id)->where('type', 'payment_to_confirm')->firstOrFail();
        $this->assertSame(PaymentClaim::class, $notice->notifiable_type);
        $this->assertSame($claim->payment_claim_id, (int) $notice->notifiable_id);
        $this->assertFalse($notice->is_read);

        $this->actingAs($t['landlord'])->post(route('landlord.paymentClaims.confirm', $claim))->assertSessionHasNoErrors();

        $this->assertTrue($notice->fresh()->is_read);
    }

    public function test_rejecting_or_withdrawing_a_report_also_clears_its_notice(): void
    {
        $t = $this->owing();
        $file = fn (string $ref) => $this->actingAs($t['tenant'])->post(route('paymentClaims.store', $t['reservation']), [
            'accept_terms' => '1', 'claim_type' => 'Monthly', 'billing_period' => '2026-08-01', 'amount' => 5000,
            'payment_method' => 'GCash', 'reference_no' => $ref, 'paid_on' => '2026-10-13',
        ])->assertSessionHasNoErrors();

        $file('REJ-1');
        $first = PaymentClaim::latest('payment_claim_id')->first();
        $this->actingAs($t['landlord'])->post(route('landlord.paymentClaims.reject', $first), ['rejection_reason' => 'Nothing arrived'])->assertSessionHasNoErrors();
        $this->assertSame(0, Notification::where('user_id', $t['landlord']->user_id)->where('type', 'payment_to_confirm')->where('is_read', false)->count());

        $file('WD-1');
        $second = PaymentClaim::latest('payment_claim_id')->first();
        $this->assertSame(1, Notification::where('user_id', $t['landlord']->user_id)->where('type', 'payment_to_confirm')->where('is_read', false)->count());
        $this->actingAs($t['tenant'])->delete(route('paymentClaims.withdraw', $second))->assertSessionHasNoErrors();
        $this->assertSame(0, Notification::where('user_id', $t['landlord']->user_id)->where('type', 'payment_to_confirm')->where('is_read', false)->count());
    }
}
