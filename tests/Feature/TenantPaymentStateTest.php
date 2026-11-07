<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\Reservation;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\TestCase;

/** A signed lease tells the tenant where the money stands, in the pill and in the button. */
class TenantPaymentStateTest extends TestCase
{
    use CreatesMarketplaceFixtures, DatabaseTransactions;

    private $tenant;

    private Reservation $reservation;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = $this->makeTenant();
        $unit = $this->makeUnit($this->makeProperty($this->makeLandlord()));

        $this->reservation = Reservation::create([
            'tenant_id' => $this->tenant->user_id,
            'property_id' => $unit->property_id,
            'unit_id' => $unit->unit_id,
            'rental_status' => 'Rental Agreement Signed',
            'reservation_date' => today(),
            'target_move_in_date' => today()->addWeek(),
        ]);
    }

    private function page(): string
    {
        return $this->actingAs($this->tenant)->get(route('reservations.index'))->assertOk()->getContent();
    }

    public function test_signed_and_unpaid_reads_payment_due(): void
    {
        $this->assertSame('due', $this->reservation->paymentState());
        $this->assertStringContainsString('Payment due', $this->page());
    }

    public function test_a_report_waiting_for_the_landlord_reads_payment_sent(): void
    {
        $this->reservation->paymentClaims()->create([
            'claim_type' => 'MoveIn', 'amount' => 7000, 'payment_method' => 'GCash', 'reference_no' => 'REF-PILL-1',
            'paid_on' => today(), 'status' => 'Pending', 'filed_by' => $this->tenant->user_id,
        ]);

        $this->assertSame('reported', $this->reservation->fresh()->paymentState());
        $this->assertStringContainsString('Payment sent', $this->page());
        $this->assertSame('Waiting for landlord', $this->reservation->fresh()->tenantNextStep()['label']);
    }

    public function test_settled_money_reads_paid_and_offers_confirm_move_in(): void
    {
        $this->reservation->forceFill(['move_in_paid_at' => now()])->save();

        $this->assertSame('paid', $this->reservation->fresh()->paymentState());
        $this->assertStringContainsString('Paid · move in', $this->page());
        $this->assertSame('Confirm move-in', $this->reservation->fresh()->tenantNextStep()['label']);
    }

    public function test_online_money_held_counts_as_paid(): void
    {
        Payment::create([
            'reservation_id' => $this->reservation->reservation_id,
            'payment_type' => 'Initial',
            'amount' => 5000,
            'status' => 'Held',
            'payment_method' => 'GCash',
        ]);

        $this->assertSame('paid', $this->reservation->fresh()->paymentState());
    }

    public function test_other_statuses_have_no_payment_state(): void
    {
        $this->reservation->forceFill(['rental_status' => 'Pending Rental Agreement'])->save();

        $this->assertNull($this->reservation->fresh()->paymentState());
    }
}
