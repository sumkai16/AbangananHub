<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\LandlordTenant;
use App\Models\PaymentClaim;
use App\Models\Reservation;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\TestCase;

/**
 * The landlord's tenant list (Tenant IDs, derived status) and the unit being held once move-in money is settled.
 * plans/landlord-tenants-contracts-direct-payments.md, phase 1.
 */
class LandlordTenantRosterTest extends TestCase
{
    use CreatesMarketplaceFixtures, DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        config(['rentals.online_payments_enabled' => false]);
        Carbon::setTestNow('2026-10-14 10:00:00');
    }

    private function reservationOn($unit, $tenant, string $status, array $extra = []): Reservation
    {
        $conversation = Conversation::create([
            'tenant_id' => $tenant->user_id, 'landlord_id' => $unit->property->landlord_id,
            'property_id' => $unit->property_id, 'unit_id' => $unit->unit_id, 'type' => 'inquiry',
        ]);

        return Reservation::create($extra + [
            'tenant_id' => $tenant->user_id, 'property_id' => $unit->property_id, 'unit_id' => $unit->unit_id,
            'conversation_id' => $conversation->conversation_id,
            'rental_status' => $status, 'reservation_date' => '2026-10-01',
            'target_move_in_date' => '2026-11-01', 'agreed_monthly_rent' => 5000, 'rent_due_day' => 1,
        ]);
    }

    public function test_tenant_numbers_count_per_landlord_and_repeat_adds_reuse_the_row(): void
    {
        $landlordA = $this->makeLandlord();
        $landlordB = $this->makeLandlord();
        $tenant1 = $this->makeTenant();
        $tenant2 = $this->makeTenant();

        $a1 = LandlordTenant::ensureFor($landlordA->user_id, $tenant1->user_id);
        $a2 = LandlordTenant::ensureFor($landlordA->user_id, $tenant2->user_id);
        $b1 = LandlordTenant::ensureFor($landlordB->user_id, $tenant2->user_id);

        $this->assertSame('TN-0001', $a1->code());
        $this->assertSame('TN-0002', $a2->code());
        $this->assertSame('TN-0001', $b1->code(), 'Each landlord counts from 1.');

        $again = LandlordTenant::ensureFor($landlordA->user_id, $tenant1->user_id);
        $this->assertSame($a1->landlord_tenant_id, $again->landlord_tenant_id);
        $this->assertSame(2, LandlordTenant::where('landlord_id', $landlordA->user_id)->count());
    }

    public function test_an_archived_tenant_comes_back_with_the_same_number(): void
    {
        $landlord = $this->makeLandlord();
        $tenant = $this->makeTenant();

        $row = LandlordTenant::ensureFor($landlord->user_id, $tenant->user_id);
        $row->update(['archived_at' => now()]);

        $back = LandlordTenant::ensureFor($landlord->user_id, $tenant->user_id);

        $this->assertNull($back->archived_at);
        $this->assertSame($row->tenant_number, $back->tenant_number);
    }

    public function test_status_is_derived_from_reservation_statuses(): void
    {
        $this->assertSame('Pending', LandlordTenant::statusFrom([]));
        $this->assertSame('Pending', LandlordTenant::statusFrom(['Inquiry', 'Cancelled']));
        $this->assertSame('Active', LandlordTenant::statusFrom(['Completed', 'Rental Agreement Signed']));
        $this->assertSame('Active', LandlordTenant::statusFrom(['Reserved']));
        $this->assertSame('Former', LandlordTenant::statusFrom(['Completed', 'Rejected']));
    }

    public function test_a_tenant_joins_the_list_on_a_signed_lease_not_on_an_inquiry(): void
    {
        $landlord = $this->makeLandlord();
        $tenant = $this->makeTenant();
        $unit = $this->makeUnit($this->makeProperty($landlord));

        $reservation = $this->reservationOn($unit, $tenant, 'Inquiry');
        $this->assertFalse(LandlordTenant::where('tenant_id', $tenant->user_id)->exists());

        $reservation->update(['rental_status' => 'Rental Agreement Signed']);

        $row = LandlordTenant::where('landlord_id', $landlord->user_id)->where('tenant_id', $tenant->user_id)->first();
        $this->assertNotNull($row);
        $this->assertSame('Active', LandlordTenant::statusFrom($row->reservations()->pluck('rental_status')));
    }

    public function test_confirming_move_in_money_holds_the_unit_and_cancelling_releases_it(): void
    {
        $landlord = $this->makeLandlord();
        $tenant = $this->makeTenant();
        $unit = $this->makeUnit($this->makeProperty($landlord));
        $reservation = $this->reservationOn($unit, $tenant, 'Rental Agreement Signed');

        $claim = PaymentClaim::create([
            'reservation_id' => $reservation->reservation_id, 'claim_type' => 'MoveIn', 'amount' => 5000,
            'payment_method' => 'GCash', 'reference_no' => 'GC1', 'paid_on' => '2026-10-13',
            'status' => 'Pending', 'filed_by' => $tenant->user_id,
        ]);

        $this->actingAs($landlord)
            ->post(route('landlord.paymentClaims.confirm', $claim))
            ->assertSessionHasNoErrors();

        $this->assertSame('Reserved', $unit->fresh()->availability_status);

        $this->actingAs($tenant)
            ->patch(route('reservations.cancel', $reservation))
            ->assertSessionHasNoErrors();

        $this->assertSame('Cancelled', $reservation->fresh()->rental_status);
        $this->assertSame('Available', $unit->fresh()->availability_status);
    }

    public function test_cancelling_an_inquiry_does_not_free_a_unit_held_for_someone_else(): void
    {
        $landlord = $this->makeLandlord();
        $unit = $this->makeUnit($this->makeProperty($landlord));

        $this->reservationOn($unit, $this->makeTenant(), 'Rental Agreement Signed', ['move_in_paid_at' => now()]);
        $unit->update(['availability_status' => 'Reserved']);

        $other = $this->makeTenant();
        $inquiry = $this->reservationOn($unit, $other, 'Inquiry');

        $this->actingAs($other)->patch(route('reservations.cancel', $inquiry))->assertSessionHasNoErrors();

        $this->assertSame('Reserved', $unit->fresh()->availability_status);
    }

    public function test_move_in_money_cannot_be_settled_on_a_unit_held_for_another_tenant(): void
    {
        $landlord = $this->makeLandlord();
        $unit = $this->makeUnit($this->makeProperty($landlord));

        $this->reservationOn($unit, $this->makeTenant(), 'Rental Agreement Signed', ['move_in_paid_at' => now()]);
        $second = $this->reservationOn($unit, $this->makeTenant(), 'Rental Agreement Signed');

        $this->actingAs($landlord)
            ->post(route('landlord.reservations.recordMoveInPayment', $second), [
                'initial_amount' => 5000, 'payment_method' => 'Cash', 'payment_date' => '2026-10-13',
            ])
            ->assertSessionHasErrors();

        $this->assertNull($second->fresh()->move_in_paid_at);
    }
}
