<?php

namespace Tests\Feature\Payments;

use App\Models\Conversation;
use App\Models\Notification;
use App\Models\Payment;
use App\Models\PaymentClaim;
use App\Models\Report;
use App\Models\Reservation;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\Support\CreatesPaymentFixtures;
use Tests\TestCase;

/**
 * Scam protection on direct payments: cash can be reported (so a landlord who never records it leaves a trace),
 * one receipt cannot be reported twice, and a rejected report can be sent to an admin.
 */
class DirectPaymentSafetyTest extends TestCase
{
    use CreatesMarketplaceFixtures, CreatesPaymentFixtures, DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        config(['rentals.online_payments_enabled' => false]);
        Carbon::setTestNow('2026-10-14 10:00:00');
    }

    private function signedReservation(): array
    {
        $landlord = $this->makeLandlord();
        $tenant = $this->makeTenant();
        $unit = $this->makeUnit($this->makeProperty($landlord));
        $conversation = Conversation::create([
            'tenant_id' => $tenant->user_id, 'landlord_id' => $landlord->user_id,
            'property_id' => $unit->property_id, 'unit_id' => $unit->unit_id, 'type' => 'inquiry',
        ]);
        $reservation = Reservation::create([
            'tenant_id' => $tenant->user_id, 'property_id' => $unit->property_id, 'unit_id' => $unit->unit_id,
            'conversation_id' => $conversation->conversation_id,
            'rental_status' => 'Rental Agreement Signed', 'reservation_date' => '2026-10-01',
            'target_move_in_date' => '2026-10-15', 'agreed_monthly_rent' => 5000, 'rent_due_day' => 15,
        ]);

        return compact('landlord', 'tenant', 'reservation', 'unit');
    }

    private function qrClaim(array $overrides = []): array
    {
        return $overrides + [
            'accept_terms' => '1', 'claim_type' => 'MoveIn', 'amount' => 10000, 'payment_method' => 'GCash',
            'reference_no' => 'GC123456', 'paid_on' => '2026-10-13',
        ];
    }

    private function cashClaim(array $overrides = []): array
    {
        return $overrides + ['accept_terms' => '1', 'claim_type' => 'MoveIn', 'amount' => 10000, 'payment_method' => 'Cash', 'paid_on' => '2026-10-13'];
    }

    // ─── cash can be reported ───────────────────────────────────

    public function test_a_tenant_can_report_cash_without_a_reference_and_the_landlord_confirms_it(): void
    {
        ['landlord' => $landlord, 'tenant' => $tenant, 'reservation' => $reservation] = $this->signedReservation();

        $this->actingAs($tenant)->post(route('paymentClaims.store', $reservation), $this->cashClaim())
            ->assertSessionHasNoErrors();

        $claim = PaymentClaim::firstOrFail();
        $this->assertTrue($claim->isCash());
        $this->assertNull($claim->reference_no);
        $this->assertSame(0, Payment::where('reservation_id', $reservation->reservation_id)->count(), 'A report is not money.');

        $note = Notification::where('user_id', $landlord->user_id)->latest('notification_id')->first();
        $this->assertStringContainsString('in cash', $note->message);

        $this->actingAs($landlord)->post(route('landlord.paymentClaims.confirm', $claim))->assertSessionHasNoErrors();

        $this->assertSame('Confirmed', $claim->fresh()->status);
        $this->assertTrue($reservation->fresh()->hasSettledInitialPayment());
        $this->assertTrue(Payment::where('reservation_id', $reservation->reservation_id)->get()->every(fn ($p) => $p->payment_method === 'Cash' && $p->payout_owed_at === null));
    }

    public function test_a_non_cash_report_still_needs_a_reference(): void
    {
        ['tenant' => $tenant, 'reservation' => $reservation] = $this->signedReservation();

        $this->actingAs($tenant)->post(route('paymentClaims.store', $reservation), $this->qrClaim(['reference_no' => '']))
            ->assertSessionHasErrors('reference_no');

        $this->assertSame(0, PaymentClaim::count());
    }

    public function test_the_landlord_sees_cash_wording_not_a_reference(): void
    {
        ['landlord' => $landlord, 'tenant' => $tenant, 'reservation' => $reservation] = $this->signedReservation();
        $this->actingAs($tenant)->post(route('paymentClaims.store', $reservation), $this->cashClaim());

        $this->actingAs($landlord)->get(route('conversations.show', $reservation->conversation_id), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()->assertSee('in cash')->assertSee('Confirm only if you actually received this cash')->assertDontSee('before you confirm');
    }

    // ─── one receipt, one report ────────────────────────────────

    public function test_the_same_reference_cannot_be_reported_twice_even_in_other_letters(): void
    {
        ['tenant' => $tenant, 'reservation' => $reservation] = $this->signedReservation();
        $other = $this->signedReservation();

        $this->actingAs($tenant)->post(route('paymentClaims.store', $reservation), $this->qrClaim(['reference_no' => 'ab12cd']))
            ->assertSessionHasNoErrors();

        $this->actingAs($other['tenant'])->post(route('paymentClaims.store', $other['reservation']), $this->qrClaim(['reference_no' => 'AB12CD']))
            ->assertSessionHasErrors('payment');

        $this->assertSame(1, PaymentClaim::count());
    }

    public function test_a_confirmed_reference_stays_used_but_a_rejected_or_withdrawn_one_is_free(): void
    {
        ['landlord' => $landlord, 'tenant' => $tenant, 'reservation' => $reservation] = $this->signedReservation();
        $other = $this->signedReservation();

        $this->actingAs($tenant)->post(route('paymentClaims.store', $reservation), $this->qrClaim());
        $claim = PaymentClaim::firstOrFail();
        $this->actingAs($landlord)->post(route('landlord.paymentClaims.reject', $claim), ['rejection_reason' => 'Typo in the number']);

        // Rejected: the number is free again (the tenant may have mistyped it).
        $this->actingAs($other['tenant'])->post(route('paymentClaims.store', $other['reservation']), $this->qrClaim())
            ->assertSessionHasNoErrors();
        $this->assertSame(2, PaymentClaim::count());

        $second = PaymentClaim::where('reservation_id', $other['reservation']->reservation_id)->firstOrFail();
        $this->actingAs($other['landlord'])->post(route('landlord.paymentClaims.confirm', $second))->assertSessionHasNoErrors();

        // Confirmed: now it is spent, so the first tenant cannot re-file it.
        $this->actingAs($tenant)->post(route('paymentClaims.store', $reservation), $this->qrClaim())
            ->assertSessionHasErrors('payment');
    }

    public function test_cash_reports_are_not_limited_by_the_reference_rule(): void
    {
        ['tenant' => $tenant, 'reservation' => $reservation] = $this->signedReservation();
        $other = $this->signedReservation();

        $this->actingAs($tenant)->post(route('paymentClaims.store', $reservation), $this->cashClaim())->assertSessionHasNoErrors();
        $this->actingAs($other['tenant'])->post(route('paymentClaims.store', $other['reservation']), $this->cashClaim())->assertSessionHasNoErrors();

        $this->assertSame(2, PaymentClaim::count());
    }

    // ─── a rejected report goes to an admin ─────────────────────

    private function rejectedClaim(): array
    {
        $fx = $this->signedReservation();
        $this->actingAs($fx['tenant'])->post(route('paymentClaims.store', $fx['reservation']), $this->qrClaim());
        $claim = PaymentClaim::firstOrFail();
        $this->actingAs($fx['landlord'])->post(route('landlord.paymentClaims.reject', $claim), ['rejection_reason' => 'Nothing in my account']);

        return $fx + ['claim' => $claim->fresh()];
    }

    public function test_a_rejected_report_can_be_sent_to_an_admin_once(): void
    {
        $fx = $this->rejectedClaim();

        $this->actingAs($fx['tenant'])->post(route('paymentClaims.dispute', $fx['claim']))->assertSessionHasNoErrors();

        $claim = $fx['claim']->fresh();
        $this->assertNotNull($claim->disputed_at);

        $report = Report::findOrFail($claim->dispute_report_id);
        $this->assertSame($fx['tenant']->user_id, $report->reporter_id);
        $this->assertSame($fx['landlord']->user_id, $report->reported_user_id);
        $this->assertSame('Pending', $report->report_status);
        $this->assertStringContainsString('GC123456', $report->report_reason);
        $this->assertStringContainsString('Nothing in my account', $report->report_reason);

        $this->assertSame(1, Notification::where('user_id', $fx['landlord']->user_id)->where('title', 'Payment sent for review')->count());

        // Once per claim.
        $this->actingAs($fx['tenant'])->post(route('paymentClaims.dispute', $claim))->assertSessionHasErrors('payment');
        $this->assertSame(1, Report::count());
    }

    public function test_only_a_rejected_report_can_be_disputed_and_only_by_its_tenant(): void
    {
        ['tenant' => $tenant, 'reservation' => $reservation] = $this->signedReservation();
        $this->actingAs($tenant)->post(route('paymentClaims.store', $reservation), $this->qrClaim());
        $pending = PaymentClaim::firstOrFail();

        $this->post(route('paymentClaims.dispute', $pending))->assertSessionHasErrors('payment');

        $fx = $this->rejectedClaim();
        $this->actingAs($this->makeTenant())->post(route('paymentClaims.dispute', $fx['claim']))->assertForbidden();
        $this->actingAs($fx['landlord'])->post(route('paymentClaims.dispute', $fx['claim']))->assertForbidden();
        $this->assertSame(0, Report::count());
    }

    public function test_the_tenant_page_shows_the_dispute_button_then_the_sent_state(): void
    {
        $fx = $this->rejectedClaim();

        $this->actingAs($fx['tenant'])->get(route('agreements.show', $fx['reservation']))
            ->assertOk()->assertSee('Send to AbangananHub for review');

        $this->post(route('paymentClaims.dispute', $fx['claim']));

        $this->get(route('agreements.show', $fx['reservation']))
            ->assertOk()->assertDontSee('Send to AbangananHub for review')->assertSee('Sent to AbangananHub for review');
    }

    // ─── what the tenant sees ───────────────────────────────────

    public function test_the_pay_page_shows_safety_tips_and_the_cash_report_button(): void
    {
        ['tenant' => $tenant, 'reservation' => $reservation] = $this->signedReservation();

        $this->actingAs($tenant)->get(route('agreements.show', $reservation))
            ->assertOk()
            ->assertSee('Stay safe when paying')
            ->assertSee('Pay only after you have seen the unit')
            ->assertSee('I&rsquo;ve handed over the cash', false);
    }

    public function test_the_landlord_chat_still_loads_when_a_reservation_has_several_reports(): void
    {
        // A rejected report plus a fresh pending one: two claims on one reservation used to make the
        // landlord's chat panel lazy-load each claim's reservation and throw (500).
        $fx = $this->rejectedClaim();
        $this->actingAs($fx['tenant'])->post(route('paymentClaims.store', $fx['reservation']), $this->qrClaim());
        $this->assertSame(2, PaymentClaim::count());

        $this->actingAs($fx['landlord'])
            ->get(route('conversations.show', $fx['reservation']->conversation_id), ['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'text/html'])
            ->assertOk()->assertSee('Payment to confirm');
    }

    // ─── what the admin sees and can do ─────────────────────────

    public function test_the_admin_sees_the_evidence_links_and_the_landlords_record(): void
    {
        $fx = $this->rejectedClaim();
        $this->actingAs($fx['tenant'])->post(route('paymentClaims.dispute', $fx['claim']));
        $report = Report::firstOrFail();

        $this->actingAs($this->makeUserWithRole('Admin'))->get(route('admin.reports.show', $report))
            ->assertOk()
            ->assertSee('Payment dispute')
            ->assertSee('GC123456')
            ->assertSee('Nothing in my account')
            ->assertSee('0 reports confirmed, 1 rejected')
            ->assertSee('1 sent to admin')
            ->assertSee(route('admin.reservations.show', $fx['reservation']), false)
            ->assertSee(route('admin.conversations.show', $fx['reservation']->conversation_id), false)
            ->assertSee('cannot refund it');
    }

    public function test_several_disputes_against_one_landlord_read_as_a_pattern(): void
    {
        $fx = $this->rejectedClaim();
        $this->actingAs($fx['tenant'])->post(route('paymentClaims.dispute', $fx['claim']));

        // Same landlord, a second reservation, a second rejected and disputed report.
        $unit2 = $this->makeUnit($this->makeProperty($fx['landlord']));
        $tenant2 = $this->makeTenant();
        $res2 = Reservation::create([
            'tenant_id' => $tenant2->user_id, 'property_id' => $unit2->property_id, 'unit_id' => $unit2->unit_id,
            'rental_status' => 'Rental Agreement Signed', 'reservation_date' => '2026-10-01',
            'target_move_in_date' => '2026-10-15', 'agreed_monthly_rent' => 5000, 'rent_due_day' => 15,
        ]);
        $this->actingAs($tenant2)->post(route('paymentClaims.store', $res2), $this->qrClaim(['reference_no' => 'OTHER99']));
        $second = PaymentClaim::where('reservation_id', $res2->reservation_id)->firstOrFail();
        $this->actingAs($fx['landlord'])->post(route('landlord.paymentClaims.reject', $second), ['rejection_reason' => 'No payment seen']);
        $this->actingAs($tenant2)->post(route('paymentClaims.dispute', $second));

        $this->actingAs($this->makeUserWithRole('Admin'))->get(route('admin.reports.show', Report::latest('report_id')->first()))
            ->assertOk()->assertSee('2 sent to admin')->assertSee('a pattern worth acting on');
    }

    public function test_the_admin_can_warn_the_landlord_without_suspending_them(): void
    {
        $fx = $this->rejectedClaim();
        $this->actingAs($fx['tenant'])->post(route('paymentClaims.dispute', $fx['claim']));
        $report = Report::firstOrFail();

        $this->actingAs($this->makeUserWithRole('Admin'))->patch(route('admin.reports.resolve', $report), [
            'admin_notes' => 'Receipt checks out; landlord should have confirmed.', 'action_taken' => 'warn_user',
        ])->assertSessionHasNoErrors();

        $this->assertSame('Resolved', $report->fresh()->report_status);
        $this->assertStringStartsWith('Warned user:', $report->fresh()->action_taken);
        $this->assertNotSame('suspended', $fx['landlord']->fresh()->account_status);

        $warning = Notification::where('user_id', $fx['landlord']->user_id)->where('title', 'Warning from AbangananHub')->first();
        $this->assertNotNull($warning);
        $this->assertStringNotContainsString($fx['tenant']->first_name, $warning->message, 'The reporter is not named.');
    }

    public function test_while_a_report_is_pending_the_other_ways_to_pay_are_hidden_until_it_is_withdrawn(): void
    {
        ['tenant' => $tenant, 'reservation' => $reservation] = $this->signedReservation();
        $this->actingAs($tenant)->post(route('paymentClaims.store', $reservation), $this->qrClaim());

        $this->get(route('agreements.show', $reservation))
            ->assertOk()
            ->assertSee('Waiting for')
            ->assertSee('withdraw this report first')
            ->assertDontSee('Pay cash')
            ->assertDontSee('Stay safe when paying');

        $this->delete(route('paymentClaims.withdraw', PaymentClaim::firstOrFail()));

        $this->get(route('agreements.show', $reservation))->assertOk()->assertSee('Pay cash')->assertSee('Stay safe when paying');
    }

    // ─── the tick-box ───────────────────────────────────────────

    public function test_a_report_without_the_tick_box_is_refused_for_qr_and_cash(): void
    {
        ['tenant' => $tenant, 'reservation' => $reservation] = $this->signedReservation();

        $this->actingAs($tenant)->post(route('paymentClaims.store', $reservation), $this->qrClaim(['accept_terms' => null]))
            ->assertSessionHasErrors('accept_terms');
        $this->post(route('paymentClaims.store', $reservation), $this->cashClaim(['accept_terms' => null]))
            ->assertSessionHasErrors('accept_terms');

        $this->assertSame(0, PaymentClaim::count());
    }

    public function test_agreeing_is_written_to_the_audit_log_with_the_route(): void
    {
        ['tenant' => $tenant, 'reservation' => $reservation] = $this->signedReservation();

        $this->actingAs($tenant)->post(route('paymentClaims.store', $reservation), $this->cashClaim());

        $row = \App\Models\AuditLog::where('action', 'payment.terms_accepted')->latest('log_id')->first();
        $this->assertNotNull($row);
        $this->assertSame('cash', $row->metadata['route']);
        $this->assertSame($tenant->user_id, $row->actor_id);
    }

    public function test_the_pay_forms_carry_the_agree_box_with_route_wording(): void
    {
        ['tenant' => $tenant, 'reservation' => $reservation] = $this->signedReservation();

        $this->actingAs($tenant)->get(route('agreements.show', $reservation))
            ->assertOk()
            ->assertSee('name="accept_terms"', false)
            ->assertSee('a QR that', false)
            ->assertSee('I handed this cash to', false)
            ->assertSee('Read the Terms');
    }

    // ─── the redesigned agreement page ──────────────────────────

    public function test_the_agreement_page_leads_with_calm_key_terms_and_a_progress_bar(): void
    {
        ['tenant' => $tenant, 'reservation' => $reservation] = $this->signedReservation();
        $reservation->update(['target_move_in_date' => '2026-11-07', 'move_out_date' => '2027-05-07']);

        $html = $this->actingAs($tenant)->get(route('agreements.show', $reservation))->assertOk()->getContent();

        $this->assertStringContainsString('Key terms', $html);
        $this->assertStringContainsString('Due every 15th of the month', $html);
        $this->assertStringContainsString('aria-label="Progress"', $html);
        // The pay step is current for a signed agreement with nothing paid.
        $this->assertMatchesRegularExpression('/aria-current="step"[^>]*>.*?Pay/s', $html);
        // The old at-a-glance label is gone, and the print-only summary table is still in the document.
        $this->assertStringNotContainsString('At a glance', $html);
        $this->assertStringContainsString('Summary of key terms', $html);
    }

    public function test_lease_clauses_collapse_on_screen_but_print_in_full(): void
    {
        ['tenant' => $tenant, 'reservation' => $reservation] = $this->signedReservation();

        $html = $this->actingAs($tenant)->get(route('agreements.show', $reservation))->assertOk()->getContent();

        // Eight standard clauses as rows you can open, the first one open, and an Expand all button.
        $this->assertSame(8, substr_count($html, '<details data-clause'));
        $this->assertSame(1, preg_match_all('/<details data-clause[^>]*\sopen\b/', $html));
        $this->assertStringContainsString('Expand all', $html);
        // A one-line gist shows while a clause is closed.
        $this->assertStringContainsString('due every 15th', $html);
        // Print: a closed row prints nothing, so every clause is also in the page as a plain print-only copy.
        $this->assertSame(2, substr_count($html, '. Ending the tenancy</'));
        $this->assertSame(2, substr_count($html, 'This document is the whole agreement between the parties.'));
    }

    public function test_other_pages_show_the_lease_as_plain_clauses(): void
    {
        ['tenant' => $tenant, 'reservation' => $reservation] = $this->signedReservation();
        $reservation->update(['rental_status' => 'Occupied']);

        $html = $this->actingAs($tenant)->get(route('tenancy.show', $reservation))->assertOk()->getContent();

        $this->assertStringNotContainsString('<details data-clause', $html);
        // Same words as before: numbered 1 to 8 as headings with paragraph text, no bullets.
        $this->assertStringContainsString('1. Premises and use</h3>', $html);
        $this->assertStringContainsString('8. Whole agreement</h3>', $html);
        $this->assertStringNotContainsString('<li class="pl-1">', $html);
    }

    public function test_lease_clauses_are_numbered_in_order_and_read_as_bullets(): void
    {
        ['tenant' => $tenant, 'reservation' => $reservation] = $this->signedReservation();

        $html = $this->actingAs($tenant)->get(route('agreements.show', $reservation))->assertOk()->getContent();

        // 1, 2, 3 ... 8, with no gaps (a counter bumped twice once gave 1, 3, 5 ...).
        preg_match_all('/<span class="block text-\[16\.5px\] font-semibold text-\[#060D26\]">(\d+)\. /', $html, $m);
        $this->assertSame(range(1, 8), array_map('intval', $m[1]));

        // Longer clauses are bullet lists; the same sentences are still in the print copy as one paragraph.
        $this->assertStringContainsString('<li class="pl-1">The Tenant makes no alterations without the Landlord&#039;s written consent.</li>', $html);
        $this->assertStringContainsString('The Tenant keeps the premises clean and in good condition and reports any damage or needed repair promptly. The Landlord handles repairs', $html);
    }
}
