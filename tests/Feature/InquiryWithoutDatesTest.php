<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\TestCase;

/**
 * A tenant still deciding can ask a landlord a question without dates (PM feedback, plans/tenant-feedback-nov-5.md).
 * The date stops being optional when a lease goes out, because a lease needs a start.
 */
class InquiryWithoutDatesTest extends TestCase
{
    use CreatesMarketplaceFixtures, DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-14 10:00:00');
    }

    /** @return array{landlord: User, tenant: User, reservation: Reservation} */
    private function reservationWithoutDates(string $status): array
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
            'conversation_id' => $conversation->conversation_id, 'rental_status' => $status,
            'reservation_date' => '2026-10-10',
        ]);

        return compact('landlord', 'tenant', 'reservation');
    }

    public function test_an_inquiry_needs_only_the_unit(): void
    {
        $tenant = $this->makeTenant();
        $unit = $this->makeUnit($this->makeProperty($this->makeLandlord()));

        $this->actingAs($tenant)->post(route('reservations.store'), [
            'unit_id' => $unit->unit_id,
            'target_move_in_date' => '',
            'target_move_out_date' => '',
            'occupants_count' => '',
            'message' => 'Is the water included in the rent?',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $reservation = Reservation::where('tenant_id', $tenant->user_id)->sole();
        $this->assertSame('Inquiry', $reservation->rental_status);
        $this->assertNull($reservation->target_move_in_date);
        $this->assertNull($reservation->target_move_out_date);
        $this->assertNull($reservation->occupants_count);
        $this->assertSame('Is the water included in the rent?', $reservation->conversation->messages()->first()->message);
    }

    public function test_a_move_out_date_without_a_move_in_date_is_refused(): void
    {
        $unit = $this->makeUnit($this->makeProperty($this->makeLandlord()));

        $this->actingAs($this->makeTenant())->post(route('reservations.store'), [
            'unit_id' => $unit->unit_id,
            'target_move_out_date' => '2027-04-30',
        ])->assertSessionHasErrors('target_move_out_date');
    }

    public function test_the_listing_form_offers_the_dates_as_optional(): void
    {
        $unit = $this->makeUnit($this->makeProperty($this->makeLandlord()));

        $this->actingAs($this->makeTenant())->get(route('properties.show', $unit->property_id))->assertOk()
            ->assertSee('Add move-in details')
            ->assertSee('You can ask for a visit in the chat once the landlord replies.');
    }

    public function test_the_landlord_can_accept_an_inquiry_with_no_dates_and_keep_talking(): void
    {
        ['landlord' => $landlord, 'reservation' => $reservation] = $this->reservationWithoutDates('Inquiry');

        $this->actingAs($landlord)->patch(route('landlord.reservations.advanceNegotiation', $reservation))
            ->assertSessionHasNoErrors();

        $this->assertSame('Under Negotiation', $reservation->fresh()->rental_status);
    }

    public function test_accept_and_send_is_refused_when_no_move_in_date_is_known(): void
    {
        ['landlord' => $landlord, 'reservation' => $reservation] = $this->reservationWithoutDates('Inquiry');

        $this->actingAs($landlord)->patch(route('landlord.reservations.acceptAndSend', $reservation), ['accept_tc' => '1'])
            ->assertSessionHas('error');

        $this->assertSame('Inquiry', $reservation->fresh()->rental_status);
    }

    public function test_the_chat_offers_only_accept_and_negotiate_when_no_move_in_date_is_known(): void
    {
        ['landlord' => $landlord, 'reservation' => $reservation] = $this->reservationWithoutDates('Inquiry');

        $this->actingAs($landlord)->getJson(route('conversations.show', $reservation->conversation))->assertOk()
            ->assertDontSee(route('landlord.reservations.acceptAndSend', $reservation))
            ->assertSee(route('landlord.reservations.advanceNegotiation', $reservation))
            ->assertSee('No move-in date yet');
    }

    public function test_an_agreement_is_refused_until_a_move_in_date_is_set(): void
    {
        ['landlord' => $landlord, 'reservation' => $reservation] = $this->reservationWithoutDates('Under Negotiation');

        $this->actingAs($landlord)->patch(route('landlord.reservations.advanceAgreement', $reservation), ['accept_tc' => '1']);
        $this->assertSame('Under Negotiation', $reservation->fresh()->rental_status);

        $this->actingAs($landlord)->patch(route('landlord.reservations.advanceAgreement', $reservation), [
            'accept_tc' => '1',
            'target_move_in_date' => '2026-11-05',
        ]);

        $reservation->refresh();
        $this->assertSame('Pending Rental Agreement', $reservation->rental_status);
        $this->assertSame('2026-11-05', $reservation->target_move_in_date->toDateString());
    }

    public function test_the_landlord_list_never_shows_the_inquiry_date_as_the_move_in_date(): void
    {
        ['landlord' => $landlord] = $this->reservationWithoutDates('Inquiry');

        $this->actingAs($landlord)->get(route('landlord.reservations.index'))->assertOk()
            ->assertSee('Not decided yet')
            ->assertDontSee('Oct 10, 2026 <span', false);
    }

    public function test_the_tenant_chat_and_reservations_pages_render_with_no_dates(): void
    {
        ['tenant' => $tenant, 'reservation' => $reservation] = $this->reservationWithoutDates('Inquiry');

        $this->actingAs($tenant)->getJson(route('conversations.show', $reservation->conversation))->assertOk();
        $this->actingAs($tenant)->get(route('conversations.index', ['active' => $reservation->conversation_id]))->assertOk();
        $this->actingAs($tenant)->get(route('reservations.index'))->assertOk();
    }
}
