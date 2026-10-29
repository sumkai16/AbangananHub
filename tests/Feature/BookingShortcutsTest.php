<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Reservation;
use App\Models\TenantDetail;
use App\Services\PaymentClaims;
use App\Support\LandlordTerms;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\Support\CreatesPaymentFixtures;
use Tests\TestCase;

/** Inquiry-to-move-in shortcuts: accept and send in one step, one-time landlord terms, one tenant consent, one-click move-in. */
class BookingShortcutsTest extends TestCase
{
    use CreatesMarketplaceFixtures, CreatesPaymentFixtures, DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        config(['rentals.online_payments_enabled' => false]);
        Carbon::setTestNow('2026-10-15 10:00:00');
    }

    private function reservationIn(string $status, array $overrides = []): array
    {
        $landlord = $this->makeLandlord();
        $tenant = $this->makeTenant();
        $unit = $this->makeUnit($this->makeProperty($landlord));
        $conversation = Conversation::create([
            'tenant_id' => $tenant->user_id, 'landlord_id' => $landlord->user_id,
            'property_id' => $unit->property_id, 'unit_id' => $unit->unit_id, 'type' => 'inquiry',
        ]);
        $reservation = Reservation::create(array_merge([
            'tenant_id' => $tenant->user_id, 'property_id' => $unit->property_id, 'unit_id' => $unit->unit_id,
            'conversation_id' => $conversation->conversation_id,
            'rental_status' => $status, 'reservation_date' => '2026-10-01',
            'target_move_in_date' => '2026-10-15', 'agreed_monthly_rent' => 5000, 'rent_due_day' => 15,
        ], $overrides));

        return compact('landlord', 'tenant', 'reservation');
    }

    public function test_accept_and_send_moves_an_inquiry_straight_to_pending_agreement(): void
    {
        ['landlord' => $landlord, 'reservation' => $reservation] = $this->reservationIn('Inquiry');

        $this->actingAs($landlord)
            ->patch(route('landlord.reservations.acceptAndSend', $reservation), ['accept_tc' => '1'])
            ->assertSessionHas('success');

        $reservation->refresh();
        $this->assertSame('Pending Rental Agreement', $reservation->rental_status);
        $this->assertNotNull($reservation->lease_snapshot);
        $this->assertNotNull($reservation->landlord_tc_accepted_at);
    }

    public function test_edited_terms_are_sent_instead_of_the_property_defaults(): void
    {
        ['landlord' => $landlord, 'reservation' => $reservation] = $this->reservationIn('Inquiry');
        $reservation->property->update(['default_lease_terms' => 'Default terms.']);

        $this->actingAs($landlord)->patch(route('landlord.reservations.acceptAndSend', $reservation), [
            'accept_tc' => '1', 'agreement_terms_notes' => 'No pets. Quiet hours after 9 PM.',
        ])->assertSessionHasNoErrors();

        $this->assertSame('No pets. Quiet hours after 9 PM.', $reservation->fresh()->agreement_terms_notes);
    }

    public function test_the_landlord_is_asked_for_the_terms_once_then_never_again(): void
    {
        ['landlord' => $landlord, 'reservation' => $first] = $this->reservationIn('Inquiry');

        $this->actingAs($landlord)
            ->patch(route('landlord.reservations.acceptAndSend', $first))
            ->assertSessionHasErrors('accept_tc');
        $this->assertSame('Inquiry', $first->fresh()->rental_status);

        $this->actingAs($landlord)->patch(route('landlord.reservations.acceptAndSend', $first), ['accept_tc' => '1']);
        $this->assertSame('direct', $landlord->fresh()->landlord_terms_payment_mode);

        // A second tenant's inquiry on the same landlord's property needs no checkbox.
        $unit = $this->makeUnit($first->property);
        $second = Reservation::create([
            'tenant_id' => $this->makeTenant()->user_id, 'property_id' => $unit->property_id, 'unit_id' => $unit->unit_id,
            'rental_status' => 'Inquiry', 'reservation_date' => '2026-10-02', 'target_move_in_date' => '2026-10-20',
        ]);
        $this->actingAs($landlord)
            ->patch(route('landlord.reservations.acceptAndSend', $second))
            ->assertSessionHasNoErrors();
        $this->assertSame('Pending Rental Agreement', $second->fresh()->rental_status);
    }

    public function test_switching_the_payment_route_asks_for_the_terms_again(): void
    {
        ['landlord' => $landlord] = $this->reservationIn('Inquiry');
        LandlordTerms::record($landlord);
        $this->assertFalse(LandlordTerms::needed($landlord->fresh()));

        config(['rentals.online_payments_enabled' => true]);
        $this->assertTrue(LandlordTerms::needed($landlord->fresh()));
    }

    public function test_accept_and_send_refuses_an_inquiry_that_is_already_past_that_stage(): void
    {
        ['landlord' => $landlord, 'reservation' => $reservation] = $this->reservationIn('Under Negotiation');

        $this->actingAs($landlord)
            ->patch(route('landlord.reservations.acceptAndSend', $reservation), ['accept_tc' => '1'])
            ->assertSessionHasErrors('reservation');
    }

    public function test_the_tenant_signs_with_one_consent_checkbox(): void
    {
        ['tenant' => $tenant, 'reservation' => $reservation] = $this->reservationIn('Under Negotiation');
        $reservation->advanceToPendingAgreement();
        TenantDetail::saveFor($tenant, [
            'emergency_contact_name' => 'Ana Cruz', 'emergency_contact_relationship' => 'Parent',
            'emergency_contact_number' => '0917 123 4567', 'id_type' => 'PhilSys', 'id_number' => '1234-5678-9012-3456',
        ]);

        $this->actingAs($tenant)->post(route('agreements.sign', $reservation), ['agree' => '1'])
            ->assertSessionHasNoErrors();

        $reservation->refresh();
        $this->assertSame('Rental Agreement Signed', $reservation->rental_status);
        $this->assertNotNull($reservation->tenant_tc_accepted_at);
    }

    public function test_signing_still_needs_the_one_checkbox(): void
    {
        ['tenant' => $tenant, 'reservation' => $reservation] = $this->reservationIn('Under Negotiation');
        $reservation->advanceToPendingAgreement();

        $this->actingAs($tenant)->post(route('agreements.sign', $reservation), [])
            ->assertSessionHasErrors('agree');
    }

    private function filedMoveIn(array $parts): \App\Models\PaymentClaim
    {
        return PaymentClaims::file($parts['reservation'], $parts['tenant'], [
            'claim_type' => 'MoveIn', 'amount' => 10000, 'payment_method' => 'GCash',
            'reference_no' => 'GC123456', 'paid_on' => '2026-10-14',
        ]);
    }

    public function test_confirming_a_move_in_payment_can_also_start_the_tenancy(): void
    {
        $parts = $this->reservationIn('Rental Agreement Signed');
        $claim = $this->filedMoveIn($parts);

        $this->actingAs($parts['landlord'])
            ->post(route('landlord.paymentClaims.confirm', $claim), ['and_move_in' => '1'])
            ->assertSessionHas('success');

        $this->assertSame('Occupied', $parts['reservation']->fresh()->rental_status);
    }

    public function test_confirming_without_the_flag_leaves_move_in_for_a_second_step(): void
    {
        $parts = $this->reservationIn('Rental Agreement Signed');
        $claim = $this->filedMoveIn($parts);

        $this->actingAs($parts['landlord'])->post(route('landlord.paymentClaims.confirm', $claim));

        $this->assertSame('Rental Agreement Signed', $parts['reservation']->fresh()->rental_status);
    }

    public function test_the_one_button_is_not_offered_before_the_move_in_date(): void
    {
        $parts = $this->reservationIn('Rental Agreement Signed', ['target_move_in_date' => '2026-11-20']);
        $this->filedMoveIn($parts);

        $this->assertFalse($parts['reservation']->fresh()->moveInDateReached());
        $this->actingAs($parts['landlord'])
            ->get(route('conversations.show', $parts['reservation']->conversation_id), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()
            ->assertSee('Confirm payment')
            ->assertDontSee('Confirm payment &amp; move-in', false);
    }

    public function test_the_one_button_is_offered_once_the_move_in_date_has_come(): void
    {
        $parts = $this->reservationIn('Rental Agreement Signed');
        $this->filedMoveIn($parts);

        $this->actingAs($parts['landlord'])
            ->get(route('conversations.show', $parts['reservation']->conversation_id), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()
            ->assertSee('Confirm payment &amp; move-in', false);
    }
}
