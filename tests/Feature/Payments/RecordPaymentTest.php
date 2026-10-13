<?php

namespace Tests\Feature\Payments;

use App\Models\Payment;
use App\Services\RentLedger;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Collection;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\Support\CreatesPaymentFixtures;
use Tests\TestCase;

/**
 * How a landlord records offline (cash / bank) rent and how that money is spread
 * over months. Pinned because this form writes money rows from a free-text amount:
 * a lost centavo, a payment on the wrong month, a refused user who still writes a
 * row, or a landlord-typed payment that ends up payout-eligible would each cost
 * real pesos.
 *
 * Tenancy: moved in 1 Aug 2026, rent 5,000 due on the 5th, today is 20 Oct 2026
 * with no grace days, so August, September and October are all open (15,000 owed).
 * The allocator's pure arithmetic is covered in RentPaymentAllocatorTest; this
 * file pins the HTTP path around it.
 */
class RecordPaymentTest extends TestCase
{
    use CreatesMarketplaceFixtures, CreatesPaymentFixtures, DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        config(['rentals.rent_overdue_grace_days' => 0]);
        $this->travelTo(now()->setDate(2026, 10, 20)->startOfDay());
    }

    // ─── Helpers ────────────────────────────────────────────────

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'payment_type'   => 'Monthly',
            'billing_period' => '2026-08-01',
            'amount'         => 5000,
            'payment_method' => 'Cash',
            'paid_at'        => '2026-10-19',
        ], $overrides);
    }

    private function record(array $tenancy, array $overrides = [], $as = null)
    {
        return $this->actingAs($as ?? $tenancy['landlord'])
            ->post(route('landlord.payments.store', $tenancy['reservation']), $this->payload($overrides));
    }

    /** @return Collection<int, Payment> oldest billing month first */
    private function rows(array $tenancy)
    {
        return Payment::where('reservation_id', $tenancy['reservation']->reservation_id)
            ->orderBy('billing_period')->orderBy('payment_id')->get();
    }

    /** [billing_period => amount] for every payment row on the tenancy. */
    private function byMonth(array $tenancy): array
    {
        return $this->rows($tenancy)
            ->mapWithKeys(fn (Payment $p) => [$p->billing_period?->toDateString() => (float) $p->amount])
            ->all();
    }

    private function totalCents(array $tenancy): int
    {
        return (int) $this->rows($tenancy)->sum(fn (Payment $p) => (int) round((float) $p->amount * 100));
    }

    private function summary(array $tenancy): array
    {
        return RentLedger::for($tenancy['reservation']->fresh())->summary();
    }

    private function assertNothingWritten(array $tenancy): void
    {
        $this->assertSame(0, $this->rows($tenancy)->count(), 'No payment row may be written.');
    }

    // ─── Allocation: one month, spill, part payment, overpayment ─

    public function test_an_amount_equal_to_one_month_clears_that_month_only(): void
    {
        $t = $this->makeTenancy();

        $this->record($t)->assertRedirect()->assertSessionHas('success', 'Payment recorded.');

        $this->assertSame(['2026-08-01' => 5000.0], $this->byMonth($t));
        $this->assertSame(10000.0, $this->summary($t)['outstanding']);
        $this->assertSame(2, $this->summary($t)['overdueCount']);
    }

    public function test_a_larger_amount_spills_into_later_months_and_sums_exactly(): void
    {
        $t = $this->makeTenancy();

        $this->record($t, ['amount' => '7500.50'])
            ->assertSessionHas('success', 'Payment recorded across 2 billing months.');

        $this->assertSame(['2026-08-01' => 5000.0, '2026-09-01' => 2500.5], $this->byMonth($t));
        $this->assertSame(750050, $this->totalCents($t), 'Not a centavo lost or invented.');
        // September is now partial: 2,500.50 of 5,000 paid.
        $this->assertSame(2499.5 + 5000.0, $this->summary($t)['outstanding']);
    }

    public function test_a_payment_covering_every_open_month_settles_the_tenancy(): void
    {
        $t = $this->makeTenancy();

        $this->record($t, ['amount' => 15000])->assertSessionHas('success', 'Payment recorded across 3 billing months.');

        $this->assertSame(['2026-08-01' => 5000.0, '2026-09-01' => 5000.0, '2026-10-01' => 5000.0], $this->byMonth($t));
        $this->assertSame(0.0, $this->summary($t)['outstanding']);
        $this->assertSame('paid', $this->summary($t)['paymentStatus']);
    }

    public function test_a_part_payment_is_recorded_as_a_partial_against_its_month(): void
    {
        $t = $this->makeTenancy();

        $this->record($t, ['amount' => 2000])->assertSessionHas('success', 'Payment recorded.');

        $this->assertSame(['2026-08-01' => 2000.0], $this->byMonth($t));
        $august = $this->summary($t);
        $this->assertSame(13000.0, $august['outstanding']);

        $period = RentLedger::for($t['reservation']->fresh())->periods()->first();
        $this->assertSame(2000.0, $period['paid']);
        $this->assertSame(3000.0, $period['balance'], 'August still owes the rest of its rent.');
    }

    public function test_a_second_payment_tops_up_the_partial_month_before_moving_on(): void
    {
        $t = $this->makeTenancy();
        $this->record($t, ['amount' => 2000]);

        $this->record($t, ['amount' => 4000]);

        $this->assertSame(
            [['2026-08-01', 2000.0], ['2026-08-01', 3000.0], ['2026-09-01', 1000.0]],
            $this->rows($t)->map(fn (Payment $p) => [$p->billing_period->toDateString(), (float) $p->amount])->all()
        );
        $this->assertSame(600000, $this->totalCents($t));
        $this->assertSame(9000.0, $this->summary($t)['outstanding']); // 4,000 Sep + 5,000 Oct
    }

    public function test_an_overpayment_beyond_the_open_months_lands_on_the_following_future_months(): void
    {
        $t = $this->makeTenancy();

        $this->record($t, ['amount' => '20000.50'])
            ->assertSessionHas('success', 'Payment recorded across 5 billing months.');

        $this->assertSame([
            '2026-08-01' => 5000.0,
            '2026-09-01' => 5000.0,
            '2026-10-01' => 5000.0,
            '2026-11-01' => 5000.0,
            '2026-12-01' => 0.5,
        ], $this->byMonth($t));
        $this->assertSame(2000050, $this->totalCents($t));

        $summary = $this->summary($t);
        $this->assertSame(0.0, $summary['outstanding'], 'Prepaid months are credit, not arrears.');
        $this->assertSame(1, $summary['prepaidCount'], 'Only the fully covered future month counts as paid ahead.');
        $this->assertSame('2026-11-01', $summary['prepaidThrough']->toDateString());
        $this->assertSame('paid_ahead', $summary['paymentStatus']);
    }

    public function test_the_split_starts_at_the_month_the_landlord_chose_not_at_the_oldest_unpaid_one(): void
    {
        $t = $this->makeTenancy();

        $this->record($t, ['billing_period' => '2026-10-01', 'amount' => 7500]);

        $this->assertSame(['2026-10-01' => 5000.0, '2026-11-01' => 2500.0], $this->byMonth($t));
        $this->assertSame(10000.0, $this->summary($t)['outstanding'], 'August and September are left untouched.');
    }

    public function test_a_mid_month_billing_date_is_stored_as_the_first_of_that_month(): void
    {
        $t = $this->makeTenancy();

        $this->record($t, ['billing_period' => '2026-09-17']);

        $this->assertSame(['2026-09-01' => 5000.0], $this->byMonth($t));
    }

    public function test_awkward_centavo_rents_still_sum_exactly(): void
    {
        $t = $this->makeTenancy(['agreed_monthly_rent' => 3333.33]);

        $this->record($t, ['amount' => '10000.00']);

        $this->assertSame(1000000, $this->totalCents($t));
        $this->assertSame(
            ['2026-08-01' => 3333.33, '2026-09-01' => 3333.33, '2026-10-01' => 3333.33, '2026-11-01' => 0.01],
            $this->byMonth($t)
        );
    }

    public function test_every_split_row_carries_the_same_method_date_reference_and_note(): void
    {
        $t = $this->makeTenancy();

        $this->record($t, [
            'amount'         => 12000,
            'payment_method' => 'Bank Transfer',
            'paid_at'        => '2026-10-15',
            'reference_no'   => 'BDO-77123',
            'payment_notes'  => 'Handed over at the office',
        ]);

        $this->assertCount(3, $this->rows($t));
        foreach ($this->rows($t) as $payment) {
            $this->assertSame('Bank Transfer', $payment->payment_method);
            $this->assertSame('2026-10-15', $payment->paid_at->toDateString());
            $this->assertSame('BDO-77123', $payment->reference_no);
            $this->assertSame('Handed over at the office', $payment->payment_notes);
            $this->assertSame('Monthly', $payment->payment_type);
        }
    }

    public function test_a_non_monthly_payment_is_one_row_as_posted_and_never_split(): void
    {
        $t = $this->makeTenancy();

        $this->record($t, ['payment_type' => 'Utility', 'billing_period' => '2026-08-01', 'amount' => 23000.75]);

        $row = $this->rows($t)->sole();
        $this->assertSame('Utility', $row->payment_type);
        $this->assertNull($row->billing_period, 'Only Monthly payments settle a month.');
        $this->assertSame('23000.75', (string) $row->amount);
        $this->assertSame(15000.0, $this->summary($t)['outstanding'], 'A utility bill must not pay rent.');
    }

    // ─── Allocation defects (written for the correct behaviour) ─

    public function test_a_start_month_that_is_already_settled_is_skipped_not_charged_a_second_rent(): void
    {
        $this->markTestIncomplete('Suspected bug: RentPaymentAllocator is fed RentLedger::unsettledPeriods(), which drops paid months, so a settled start month falls back to a full monthlyRent instead of 0 (PaymentController.php:351-356, RentPaymentAllocator.php:57-59).');

        $t = $this->makeTenancy();
        $this->makeRecordedPayment($t['reservation'], $t['landlord'], ['billing_period' => '2026-08-01', 'amount' => 5000]);

        $this->record($t, ['billing_period' => '2026-08-01', 'amount' => 5000]);

        $this->assertSame(5000.0, $this->rows($t)->filter(fn (Payment $p) => $p->billing_period->toDateString() === '2026-08-01')->sum(fn ($p) => (float) $p->amount), 'August was already paid; it must not take another 5,000.');
        $this->assertSame(5000.0, $this->byMonth($t)['2026-09-01'] ?? 0.0);
    }

    public function test_a_future_month_that_is_part_covered_only_takes_its_remaining_balance(): void
    {
        $this->markTestIncomplete('Suspected bug: unsettledPeriods() excludes future months, so a part-prepaid November is treated as owing a full rent and is overpaid (RentLedger.php:322, RentPaymentAllocator.php:57-60).');

        $t = $this->makeTenancy();
        $this->record($t, ['amount' => 17500]); // Aug-Oct, plus 2,500 of November

        $this->record($t, ['billing_period' => '2026-11-01', 'amount' => 5000]);

        $november = $this->rows($t)->filter(fn (Payment $p) => $p->billing_period->toDateString() === '2026-11-01')->sum(fn ($p) => (float) $p->amount);
        $this->assertSame(5000.0, $november, 'November costs 5,000 in total, the rest belongs to December.');
        $this->assertSame(2500.0, $this->byMonth($t)['2026-12-01'] ?? 0.0);
    }

    // ─── Validation ─────────────────────────────────────────────

    public function test_the_amount_must_be_a_number_between_one_and_a_million(): void
    {
        $t = $this->makeTenancy();

        foreach ([0, -100, '0.99', 'abc', '', 1000001] as $bad) {
            $this->record($t, ['amount' => $bad])->assertSessionHasErrors('amount');
        }
        $this->assertNothingWritten($t);

        $this->record($t, ['amount' => 1])->assertSessionDoesntHaveErrors();
        $this->assertSame(100, $this->totalCents($t), 'The smallest allowed payment is 1 peso.');
    }

    public function test_the_payment_method_must_come_from_the_allowed_list(): void
    {
        $t = $this->makeTenancy();

        foreach (['Bitcoin', 'cash', '', 'PayMongo'] as $bad) {
            $this->record($t, ['payment_method' => $bad])->assertSessionHasErrors('payment_method');
        }
        $this->assertNothingWritten($t);

        foreach (['Cash', 'GCash', 'Bank Transfer', 'Maya', 'Check', 'Other'] as $method) {
            $this->record($t, ['payment_method' => $method, 'billing_period' => '2026-08-01', 'amount' => 1])->assertSessionDoesntHaveErrors();
        }
        $this->assertSame(
            ['Cash', 'GCash', 'Bank Transfer', 'Maya', 'Check', 'Other'],
            $this->rows($t)->pluck('payment_method')->all()
        );
    }

    public function test_a_payment_cannot_be_dated_in_the_future(): void
    {
        $t = $this->makeTenancy();

        $this->record($t, ['paid_at' => '2026-10-21'])
            ->assertSessionHasErrors(['paid_at' => 'A payment cannot be recorded for a future date.']);
        $this->record($t, ['paid_at' => ''])->assertSessionHasErrors('paid_at');
        $this->record($t, ['paid_at' => 'not-a-date'])->assertSessionHasErrors('paid_at');
        $this->assertNothingWritten($t);

        $this->record($t, ['paid_at' => '2026-10-20'])->assertSessionDoesntHaveErrors();
        $this->assertSame(1, $this->rows($t)->count(), 'Today is allowed.');
    }

    public function test_a_monthly_payment_needs_a_billing_month(): void
    {
        $t = $this->makeTenancy();

        $this->record($t, ['billing_period' => null])
            ->assertSessionHasErrors(['billing_period' => 'Choose which month this rent payment covers.']);
        $this->record($t, ['billing_period' => 'sometime'])->assertSessionHasErrors('billing_period');

        $this->assertNothingWritten($t);
    }

    public function test_the_payment_type_must_be_a_known_type(): void
    {
        $t = $this->makeTenancy();

        $this->record($t, ['payment_type' => 'Refund'])->assertSessionHasErrors('payment_type');
        $this->record($t, ['payment_type' => ''])->assertSessionHasErrors('payment_type');

        $this->assertNothingWritten($t);
    }

    public function test_reference_and_notes_are_optional_and_length_limited(): void
    {
        $t = $this->makeTenancy();

        $this->record($t, ['reference_no' => str_repeat('R', 256)])->assertSessionHasErrors('reference_no');
        $this->record($t, ['payment_notes' => str_repeat('n', 1001)])->assertSessionHasErrors('payment_notes');
        $this->assertNothingWritten($t);

        $this->record($t, ['billing_period' => '2026-08-01'])->assertSessionDoesntHaveErrors();
        $this->record($t, ['billing_period' => '2026-09-01', 'reference_no' => str_repeat('R', 255)])->assertSessionDoesntHaveErrors();

        $rows = $this->rows($t);
        $this->assertNull($rows[0]->reference_no);
        $this->assertNull($rows[0]->payment_notes);
        $this->assertSame(255, strlen($rows[1]->reference_no));
    }

    // ─── Correcting a voided payment (replaces_payment_id) ──────

    public function test_a_correction_links_back_to_the_voided_payment_on_the_same_tenancy(): void
    {
        $t = $this->makeTenancy();
        $voided = $this->makeRecordedPayment($t['reservation'], $t['landlord'], ['amount' => 500, 'status' => 'Voided', 'void_reason' => 'wrong_amount']);

        $this->record($t, ['replaces_payment_id' => $voided->payment_id, 'amount' => 5000])->assertRedirect();

        $replacement = $this->rows($t)->firstWhere('status', 'Paid');
        $this->assertSame($voided->payment_id, $replacement->replaces_payment_id);
        $this->assertSame('Voided', $voided->fresh()->status, 'The original stays voided on record.');
        $this->assertSame(10000.0, $this->summary($t)['outstanding'], 'Only the correction counts, not the voided 500.');
    }

    public function test_a_split_correction_links_every_row_to_the_voided_payment(): void
    {
        $t = $this->makeTenancy();
        $voided = $this->makeRecordedPayment($t['reservation'], $t['landlord'], ['amount' => 500, 'status' => 'Voided']);

        $this->record($t, ['replaces_payment_id' => $voided->payment_id, 'amount' => 8000]);

        $replacements = $this->rows($t)->where('status', 'Paid');
        $this->assertCount(2, $replacements);
        $this->assertSame([$voided->payment_id], $replacements->pluck('replaces_payment_id')->unique()->values()->all());
    }

    public function test_a_payment_that_is_not_voided_cannot_be_replaced(): void
    {
        $t = $this->makeTenancy();
        $live = $this->makeRecordedPayment($t['reservation'], $t['landlord'], ['billing_period' => '2026-08-01', 'amount' => 5000]);

        $this->record($t, ['billing_period' => '2026-09-01', 'replaces_payment_id' => $live->payment_id])->assertRedirect();

        $new = $this->rows($t)->first(fn (Payment $p) => $p->billing_period->toDateString() === '2026-09-01');
        $this->assertNotNull($new, 'The payment itself is still recorded.');
        $this->assertNull($new->replaces_payment_id, 'A live payment must not be linked as "replaced".');
    }

    public function test_a_voided_payment_on_another_tenancy_cannot_be_linked(): void
    {
        $mine = $this->makeTenancy();
        $theirs = $this->makeTenancy();
        $foreignVoid = $this->makeRecordedPayment($theirs['reservation'], $theirs['landlord'], ['status' => 'Voided']);

        $this->record($mine, ['replaces_payment_id' => $foreignVoid->payment_id])->assertRedirect();

        $this->assertNull($this->rows($mine)->sole()->replaces_payment_id, 'Another landlord\'s row must never be pointed at.');
        $this->assertSame(1, Payment::where('reservation_id', $theirs['reservation']->reservation_id)->count());
    }

    public function test_a_replaces_id_that_does_not_exist_is_ignored_not_an_error(): void
    {
        $t = $this->makeTenancy();

        $this->record($t, ['replaces_payment_id' => 99999999])->assertRedirect()->assertSessionDoesntHaveErrors();

        $this->assertNull($this->rows($t)->sole()->replaces_payment_id);
        $this->record($t, ['replaces_payment_id' => 'abc'])->assertSessionHasErrors('replaces_payment_id');
    }

    // ─── Authorization ──────────────────────────────────────────

    public function test_another_landlord_cannot_record_a_payment_on_this_tenancy(): void
    {
        $t = $this->makeTenancy();

        $this->record($t, [], $this->makeLandlord())->assertForbidden();

        $this->assertNothingWritten($t);
    }

    public function test_the_tenant_cannot_record_a_payment_on_their_own_tenancy(): void
    {
        $t = $this->makeTenancy();

        $this->record($t, [], $t['tenant'])->assertForbidden();

        $this->assertNothingWritten($t);
    }

    public function test_a_guest_is_sent_to_login_and_nothing_is_written(): void
    {
        $t = $this->makeTenancy();

        $this->post(route('landlord.payments.store', $t['reservation']), $this->payload())->assertRedirect(route('login'));

        $this->assertNothingWritten($t);
    }

    public function test_payments_cannot_be_added_unless_the_tenancy_is_occupied(): void
    {
        foreach (['Completed', 'Cancelled', 'Rejected', 'Approved', 'Inquiry'] as $status) {
            $t = $this->makeTenancy(['rental_status' => $status]);

            $this->record($t)->assertForbidden();

            $this->assertNothingWritten($t);
        }
    }

    public function test_a_refused_request_writes_nothing_even_when_it_would_split_across_months(): void
    {
        $t = $this->makeTenancy();

        $this->record($t, ['amount' => 15000], $this->makeLandlord())->assertForbidden();

        $this->assertNothingWritten($t);
        $this->assertSame(15000.0, $this->summary($t)['outstanding']);
    }

    // ─── What a landlord-recorded row is ────────────────────────

    public function test_recorded_rows_are_landlord_asserted_paid_and_never_payout_eligible(): void
    {
        $t = $this->makeTenancy();

        // GCash and Bank Transfer sound like platform money; they must still be 'Paid'.
        $this->record($t, ['amount' => 10000, 'payment_method' => 'GCash']);

        $rows = $this->rows($t);
        $this->assertCount(2, $rows);
        foreach ($rows as $payment) {
            $this->assertSame($t['landlord']->user_id, $payment->recorded_by);
            $this->assertTrue($payment->isManuallyRecorded());
            $this->assertSame('Paid', $payment->status, 'Landlord-recorded money is never escrow (Held).');
            $this->assertNull($payment->payout_status, 'It must never enter the payout queue.');
            $this->assertNull($payment->released_by);
            $this->assertNull($payment->paymongo_payment_id);
            $this->assertNull($payment->paymongo_checkout_session_id);
            $this->assertNull($payment->voided_at);
            $this->assertSame($t['reservation']->reservation_id, $payment->reservation_id);
        }
    }

    public function test_a_status_or_payout_field_smuggled_into_the_request_is_ignored(): void
    {
        $t = $this->makeTenancy();

        $this->record($t, [
            'status'        => 'Held',
            'payout_status' => 'Pending Payout',
            'recorded_by'   => $t['tenant']->user_id,
            'released_by'   => $t['landlord']->user_id,
        ]);

        $payment = $this->rows($t)->sole();
        $this->assertSame('Paid', $payment->status);
        $this->assertNull($payment->payout_status);
        $this->assertSame($t['landlord']->user_id, $payment->recorded_by);
        $this->assertNull($payment->released_by);
    }

    public function test_a_payment_is_written_to_the_selected_tenancy_only(): void
    {
        $mine = $this->makeTenancy();
        $other = $this->makeTenancy(landlord: $mine['landlord']);

        $this->record($mine, ['amount' => 8000]);

        $this->assertSame(2, $this->rows($mine)->count());
        $this->assertSame(0, $this->rows($other)->count());
    }

    // ─── API: POST /api/v1/landlord/tenancies/{reservation}/payments ─

    private function apiRecord(array $tenancy, array $overrides = [], $as = null)
    {
        Sanctum::actingAs($as ?? $tenancy['landlord']);

        return $this->postJson(
            '/api/v1/landlord/tenancies/' . $tenancy['reservation']->reservation_id . '/payments',
            $this->payload($overrides)
        );
    }

    public function test_the_api_records_a_landlord_asserted_payment(): void
    {
        $t = $this->makeTenancy();

        $this->apiRecord($t, ['reference_no' => 'API-1', 'payment_method' => 'Maya'])
            ->assertCreated()
            ->assertJson(['message' => 'Payment recorded.']);

        $payment = $this->rows($t)->sole();
        $this->assertSame('2026-08-01', $payment->billing_period->toDateString());
        $this->assertSame('5000.00', (string) $payment->amount);
        $this->assertSame('Maya', $payment->payment_method);
        $this->assertSame('API-1', $payment->reference_no);
        $this->assertSame('Paid', $payment->status);
        $this->assertSame($t['landlord']->user_id, $payment->recorded_by);
        $this->assertNull($payment->payout_status);
        $this->assertSame(10000.0, $this->summary($t)['outstanding']);
    }

    public function test_the_api_refuses_another_landlord_a_tenant_and_a_closed_tenancy(): void
    {
        $t = $this->makeTenancy();

        $this->apiRecord($t, [], $this->makeLandlord())->assertForbidden();
        $this->apiRecord($t, [], $t['tenant'])->assertForbidden();
        $this->assertNothingWritten($t);

        $closed = $this->makeTenancy(['rental_status' => 'Completed']);
        $this->apiRecord($closed)->assertForbidden();
        $this->assertNothingWritten($closed);

        $this->app['auth']->forgetGuards();
        $this->postJson('/api/v1/landlord/tenancies/' . $t['reservation']->reservation_id . '/payments', $this->payload())->assertUnauthorized();
        $this->assertNothingWritten($t);
    }

    public function test_the_api_applies_the_same_validation(): void
    {
        $t = $this->makeTenancy();

        $this->apiRecord($t, ['amount' => 0])->assertUnprocessable()->assertJsonValidationErrors('amount');
        $this->apiRecord($t, ['payment_method' => 'Bitcoin'])->assertUnprocessable()->assertJsonValidationErrors('payment_method');
        $this->apiRecord($t, ['paid_at' => '2026-10-21'])->assertUnprocessable()->assertJsonValidationErrors('paid_at');
        $this->apiRecord($t, ['billing_period' => null])->assertUnprocessable()->assertJsonValidationErrors('billing_period');

        $this->assertNothingWritten($t);
    }

    public function test_the_api_spreads_an_overpayment_over_months_like_the_web_form_does(): void
    {
        $this->markTestIncomplete('Suspected bug: the API store() writes one row on the chosen month and never calls RentPaymentAllocator, so 10,000 against 5,000 rent overpays one month (Api/Landlord/PaymentController.php:112-125).');

        $t = $this->makeTenancy();

        $this->apiRecord($t, ['amount' => 10000])->assertCreated();

        $this->assertSame(['2026-08-01' => 5000.0, '2026-09-01' => 5000.0], $this->byMonth($t));
        $this->assertSame(5000.0, $this->summary($t)['outstanding']);
    }
}
