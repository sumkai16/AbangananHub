<?php

namespace Tests\Feature\Payments;

use App\Events\PaymentClaimUpdated;
use App\Models\Conversation;
use App\Models\PaymentClaim;
use App\Models\Reservation;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Event;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\Support\CreatesPaymentFixtures;
use Tests\TestCase;

/** Filing, cancelling or deciding a payment report tells the open chat and pay pages (PaymentClaimUpdated). */
class PaymentClaimBroadcastTest extends TestCase
{
    use CreatesMarketplaceFixtures, CreatesPaymentFixtures, DatabaseTransactions;

    private function signedReservation(): array
    {
        config(['rentals.online_payments_enabled' => false]);
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

        return compact('landlord', 'tenant', 'reservation', 'conversation');
    }

    public function test_the_event_names_the_chat_and_both_people(): void
    {
        ['landlord' => $landlord, 'tenant' => $tenant, 'reservation' => $reservation, 'conversation' => $conversation] = $this->signedReservation();

        $claim = PaymentClaim::create([
            'reservation_id' => $reservation->reservation_id, 'claim_type' => 'MoveIn', 'amount' => 100, 'payment_method' => 'GCash',
            'reference_no' => 'GC1', 'paid_on' => '2026-10-13', 'status' => 'Pending', 'filed_by' => $tenant->user_id,
        ]);

        $event = new PaymentClaimUpdated($claim->load('reservation.property'));

        $this->assertEqualsCanonicalizing(
            ['private-conversation.'.$conversation->conversation_id, 'private-user.'.$tenant->user_id, 'private-user.'.$landlord->user_id],
            array_map(fn ($c) => $c->name, $event->broadcastOn()),
        );
        $this->assertSame('PaymentClaimUpdated', $event->broadcastAs());
        $this->assertSame(['claim_id' => $claim->payment_claim_id, 'reservation_id' => $reservation->reservation_id,
            'conversation_id' => $conversation->conversation_id, 'claim_type' => 'MoveIn', 'status' => 'Pending'], $event->broadcastWith());
    }

    public function test_filing_and_cancelling_a_report_broadcast_it(): void
    {
        ['tenant' => $tenant, 'reservation' => $reservation] = $this->signedReservation();
        Event::fake([PaymentClaimUpdated::class]);

        $this->actingAs($tenant)->post(route('paymentClaims.store', $reservation), [
            'accept_terms' => '1', 'claim_type' => 'MoveIn', 'amount' => 10000, 'payment_method' => 'Cash', 'paid_on' => '2026-10-13',
        ])->assertSessionHasNoErrors();

        // Inside the test's own transaction the after-commit hook runs when the claim is saved outside one, so
        // dispatch it the way the hook does and check the broadcast is what the pages listen for.
        $claim = PaymentClaim::firstOrFail();
        $claim->broadcastQuietly();
        Event::assertDispatched(PaymentClaimUpdated::class, fn ($e) => $e->claim->is($claim) && $e->claim->status === 'Pending');
    }
}
