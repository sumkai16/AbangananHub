<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\PaymentClaim;
use App\Models\Reservation;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\TestCase;

/**
 * The Reservations page is the request pipeline only: from the first inquiry to a signed agreement whose move-in money
 * is not confirmed yet. Leases (Reserved, Occupied, Completed, or signed and paid) are on Contracts.
 * plans/reservations-page-pipeline.md.
 */
class ReservationsPipelineTest extends TestCase
{
    use CreatesMarketplaceFixtures, DatabaseTransactions;

    private $landlord;

    private $property;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-14 10:00:00');
        $this->landlord = $this->makeLandlord();
        $this->property = $this->makeProperty($this->landlord);
    }

    private function request(string $status, string $firstName, array $extra = []): Reservation
    {
        $tenant = $this->makeTenant();
        $tenant->update(['first_name' => $firstName]);
        $unit = $this->makeUnit($this->property, ['unit_label' => 'Unit '.$firstName]);
        $conversation = Conversation::create([
            'tenant_id' => $tenant->user_id, 'landlord_id' => $this->landlord->user_id,
            'property_id' => $unit->property_id, 'unit_id' => $unit->unit_id, 'type' => 'inquiry',
        ]);

        return Reservation::create($extra + [
            'tenant_id' => $tenant->user_id, 'property_id' => $unit->property_id, 'unit_id' => $unit->unit_id,
            'conversation_id' => $conversation->conversation_id, 'rental_status' => $status,
            'reservation_date' => '2026-10-10', 'target_move_in_date' => '2026-11-01', 'occupants_count' => 1,
            'agreed_monthly_rent' => 5000,
        ]);
    }

    private function page(array $query = [])
    {
        return $this->actingAs($this->landlord)->get(route('landlord.reservations.index', $query))->assertOk();
    }

    public function test_only_open_requests_are_listed_and_leases_stay_on_contracts(): void
    {
        $this->request('Inquiry', 'Inquirer');
        $this->request('Under Negotiation', 'Discusser');
        $this->request('Pending Rental Agreement', 'Signer');
        $this->request('Rental Agreement Signed', 'Payer');
        $this->request('Rental Agreement Signed', 'Paidup', ['move_in_paid_at' => now()]);
        $this->request('Occupied', 'Resident');
        $this->request('Reserved', 'Waiter');
        $this->request('Completed', 'Leaver');
        $this->request('Rejected', 'Declined');

        $this->page()
            ->assertSee('Inquirer')->assertSee('Discusser')->assertSee('Signer')->assertSee('Payer')
            ->assertDontSee('Paidup')->assertDontSee('Resident')->assertDontSee('Waiter')->assertDontSee('Leaver')
            ->assertDontSee('Declined');

        $this->page(['stage' => 'closed'])->assertSee('Declined')->assertDontSee('Inquirer');
    }

    public function test_each_stage_tab_shows_its_requests_with_the_next_step(): void
    {
        $this->request('Inquiry', 'Inquirer');
        $this->request('Under Negotiation', 'Discusser');
        $this->request('Pending Rental Agreement', 'Signer');

        $this->page(['stage' => 'new'])->assertSee('Inquirer')->assertDontSee('Discusser')->assertSee('Accept');
        $this->page(['stage' => 'discussing'])->assertSee('Discusser')->assertDontSee('Inquirer')->assertSee('Send agreement');
        $this->page(['stage' => 'agreement'])->assertSee('Signer')->assertSee('Signer signs');
    }

    public function test_a_reported_payment_is_counted_and_offered_for_confirmation(): void
    {
        $signed = $this->request('Rental Agreement Signed', 'Payer');
        $claim = PaymentClaim::create([
            'reservation_id' => $signed->reservation_id, 'claim_type' => 'MoveIn', 'amount' => 7000,
            'payment_method' => 'GCash', 'reference_no' => 'GC777', 'paid_on' => '2026-10-13',
            'status' => 'Pending', 'filed_by' => $signed->tenant_id,
        ]);
        $this->request('Rental Agreement Signed', 'Quiet');

        $this->page(['stage' => 'payment'])
            ->assertSee('Payment reported')
            ->assertSee('₱7,000.00 reported')
            ->assertSee(route('landlord.paymentClaims.confirm', $claim), false)
            ->assertSee('Quiet pays you');
    }

    public function test_another_landlords_requests_never_appear(): void
    {
        $other = $this->makeLandlord();
        $stranger = $this->makeTenant();
        $stranger->update(['first_name' => 'Stranger']);
        $unit = $this->makeUnit($this->makeProperty($other));
        Reservation::create([
            'tenant_id' => $stranger->user_id, 'property_id' => $unit->property_id, 'unit_id' => $unit->unit_id,
            'rental_status' => 'Inquiry', 'reservation_date' => '2026-10-10',
        ]);

        $this->page()->assertDontSee('Stranger');
    }

    public function test_the_export_follows_the_stage(): void
    {
        $this->request('Inquiry', 'Inquirer');
        $this->request('Occupied', 'Resident');

        $csv = $this->actingAs($this->landlord)->get(route('landlord.reservations.export'))->assertOk()->streamedContent();

        $this->assertStringContainsString('Inquirer', $csv);
        $this->assertStringNotContainsString('Resident', $csv);
    }

    public function test_rate_tenant_is_on_the_contract_modal(): void
    {
        $resident = $this->request('Occupied', 'Resident');

        $this->actingAs($this->landlord)->get(route('landlord.contracts.show', $resident))
            ->assertOk()->assertSee(route('landlord.reservations.rateTenant', $resident), false);
    }
}
