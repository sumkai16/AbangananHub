<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Reservation;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\TestCase;

/**
 * Steps 2 and 6 of the reservation flow: the tenant's inquiry asks for move-in, move-out and occupants; the landlord
 * sends the agreement with the final terms, and the move-in amount follows it. plans/reservation-final-terms.md.
 */
class ReservationTermsTest extends TestCase
{
    use CreatesMarketplaceFixtures, DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-14 10:00:00');
    }

    private function inquire($tenant, $unit, array $overrides = [])
    {
        return $this->actingAs($tenant)->post(route('reservations.store'), $overrides + [
            'unit_id' => $unit->unit_id,
            'target_move_in_date' => '2026-11-01',
            'occupants_count' => 2,
        ]);
    }

    /** An accepted inquiry (Under Negotiation) on a ₱5,000 unit with a ₱5,000 deposit and room for 2. */
    private function negotiating(): array
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
            'conversation_id' => $conversation->conversation_id, 'rental_status' => 'Under Negotiation',
            'reservation_date' => '2026-10-10', 'target_move_in_date' => '2026-11-01', 'occupants_count' => 1,
        ]);

        return compact('landlord', 'tenant', 'unit', 'reservation');
    }

    public function test_an_inquiry_records_the_dates_and_occupants(): void
    {
        $landlord = $this->makeLandlord();
        $tenant = $this->makeTenant();
        $unit = $this->makeUnit($this->makeProperty($landlord));

        $this->inquire($tenant, $unit, ['target_move_out_date' => '2027-04-30'])->assertSessionHasNoErrors();

        $reservation = Reservation::where('tenant_id', $tenant->user_id)->sole();
        $this->assertSame('2026-11-01', $reservation->target_move_in_date->toDateString());
        $this->assertSame('2027-04-30', $reservation->target_move_out_date->toDateString());
        $this->assertSame(2, $reservation->occupants_count);
    }

    public function test_move_out_is_optional_but_move_in_and_occupants_are_required(): void
    {
        $unit = $this->makeUnit($this->makeProperty($this->makeLandlord()));

        $this->inquire($this->makeTenant(), $unit)->assertSessionHasNoErrors();
        $this->inquire($this->makeTenant(), $unit, ['target_move_in_date' => ''])->assertSessionHasErrors('target_move_in_date');
        $this->inquire($this->makeTenant(), $unit, ['occupants_count' => ''])->assertSessionHasErrors('occupants_count');
    }

    public function test_an_inquiry_is_refused_over_the_units_limit_or_for_under_a_month(): void
    {
        $unit = $this->makeUnit($this->makeProperty($this->makeLandlord()));   // occupancy_limit 2

        $this->inquire($this->makeTenant(), $unit, ['occupants_count' => 3])->assertSessionHasErrors('occupants_count');
        $this->inquire($this->makeTenant(), $unit, ['target_move_out_date' => '2026-11-20'])->assertSessionHasErrors('target_move_out_date');
    }

    public function test_the_landlord_sends_the_agreement_with_the_final_terms(): void
    {
        ['landlord' => $landlord, 'reservation' => $reservation] = $this->negotiating();

        $this->actingAs($landlord)->patch(route('landlord.reservations.advanceAgreement', $reservation), [
            'agreed_monthly_rent' => 4500,
            'security_deposit' => 3000,
            'advance_months' => 2,
            'target_move_in_date' => '2026-11-05',
            'target_move_out_date' => '2027-05-04',
            'rent_due_day' => 5,
            'occupants_count' => 2,
            'agreement_terms_notes' => 'No pets.',
            'accept_tc' => '1',
        ])->assertSessionHas('success');

        $reservation->refresh();
        $this->assertSame('Pending Rental Agreement', $reservation->rental_status);
        $this->assertSame('4500.00', $reservation->agreed_monthly_rent);
        $this->assertSame('2026-11-05', $reservation->target_move_in_date->toDateString());
        $this->assertSame(5, $reservation->rent_due_day);
        $this->assertSame(2, $reservation->occupants_count);
        $this->assertEquals(3000, $reservation->lease_snapshot['deposit']);
        $this->assertSame(2, $reservation->lease_snapshot['advance_months']);
        $this->assertSame('No pets.', $reservation->lease_snapshot['extra_terms']);
        $this->assertSame(12000.0, $reservation->moveInDue(), '2 × ₱4,500 + ₱3,000');
    }

    public function test_a_bad_term_is_refused_with_a_message_and_nothing_changes(): void
    {
        ['landlord' => $landlord, 'reservation' => $reservation] = $this->negotiating();

        $this->actingAs($landlord)->patch(route('landlord.reservations.advanceAgreement', $reservation), [
            'occupants_count' => 5, 'accept_tc' => '1',
        ])->assertSessionHas('error');

        $this->assertSame('Under Negotiation', $reservation->fresh()->rental_status);
    }

    public function test_without_terms_the_agreement_keeps_todays_defaults(): void
    {
        ['landlord' => $landlord, 'reservation' => $reservation] = $this->negotiating();

        Sanctum::actingAs($landlord);
        $this->patchJson("/api/v1/landlord/reservations/{$reservation->reservation_id}/advance-agreement", [])
            ->assertOk();

        $reservation->refresh();
        $this->assertSame('5000.00', $reservation->agreed_monthly_rent);
        $this->assertSame(1, $reservation->advanceMonths());
        $this->assertSame(10000.0, $reservation->moveInDue(), 'One month + the listing deposit, as before');
    }

    public function test_another_landlord_cannot_send_the_agreement(): void
    {
        ['reservation' => $reservation] = $this->negotiating();

        $this->actingAs($this->makeLandlord())->patch(route('landlord.reservations.advanceAgreement', $reservation), [
            'accept_tc' => '1',
        ])->assertForbidden();
    }

    public function test_no_advance_means_only_the_deposit_is_due_at_move_in(): void
    {
        ['landlord' => $landlord, 'reservation' => $reservation] = $this->negotiating();

        $this->actingAs($landlord)->patch(route('landlord.reservations.advanceAgreement', $reservation), [
            'advance_months' => 0, 'accept_tc' => '1',
        ])->assertSessionHas('success');

        $this->assertSame(5000.0, $reservation->fresh()->moveInDue());
    }
}
