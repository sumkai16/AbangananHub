<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\Reservation;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\Support\CreatesPaymentFixtures;
use Tests\TestCase;

/**
 * One holder per unit from the moment an agreement is sent until the tenancy ends: a second agreement on the
 * same unit is refused, checkout will not take money for a unit someone else already holds, and nobody can
 * be set Occupied on a unit that another reservation already occupies.
 */
class UnitExclusivityTest extends TestCase
{
    use CreatesMarketplaceFixtures, CreatesPaymentFixtures, DatabaseTransactions;

    private const REASON = 'Another reservation on this unit already has an agreement in progress, or the unit is occupied. Cancel or complete that one first.';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.paymongo.secret_key' => 'sk_test_x']);
        Carbon::setTestNow('2026-10-14 10:00:00');
    }

    /** A second reservation by another tenant on the same unit as $first. */
    private function competitor(Reservation $first, string $status): Reservation
    {
        return Reservation::create([
            'tenant_id'           => $this->makeTenant()->user_id,
            'property_id'         => $first->property_id,
            'unit_id'             => $first->unit_id,
            'rental_status'       => $status,
            'reservation_date'    => '2026-08-01',
            'target_move_in_date' => '2026-08-01',
        ]);
    }

    private function held(Reservation $reservation): Payment
    {
        return $this->makePayment($reservation, [
            'payment_type' => 'Initial', 'billing_period' => null, 'status' => 'Held', 'payment_method' => 'GCash',
        ]);
    }

    public function test_a_second_agreement_on_the_same_unit_is_refused_on_the_web(): void
    {
        $t = $this->makeTenancy(['rental_status' => 'Under Negotiation']);
        $second = $this->competitor($t['reservation'], 'Under Negotiation');

        $this->actingAs($t['landlord'])
            ->patch(route('landlord.reservations.advanceAgreement', $t['reservation']), ['accept_tc' => '1'])
            ->assertSessionHas('success');

        $this->actingAs($t['landlord'])
            ->patch(route('landlord.reservations.advanceAgreement', $second), ['accept_tc' => '1'])
            ->assertSessionHasErrors(['reservation' => self::REASON]);

        $this->assertSame('Pending Rental Agreement', $t['reservation']->fresh()->rental_status, 'the first still stands');
        $this->assertSame('Under Negotiation', $second->fresh()->rental_status);
    }

    public function test_a_second_agreement_on_the_same_unit_is_refused_on_the_api(): void
    {
        $t = $this->makeTenancy(['rental_status' => 'Under Negotiation']);
        $second = $this->competitor($t['reservation'], 'Under Negotiation');
        Sanctum::actingAs($t['landlord'], ['*']);

        $this->patchJson("/api/v1/landlord/reservations/{$t['reservation']->reservation_id}/advance-agreement")->assertOk();

        $this->patchJson("/api/v1/landlord/reservations/{$second->reservation_id}/advance-agreement")
            ->assertStatus(422)
            ->assertJsonPath('errors.reservation.0', self::REASON);

        $this->assertSame('Under Negotiation', $second->fresh()->rental_status);
    }

    public function test_an_agreement_is_refused_while_the_unit_is_occupied(): void
    {
        $t = $this->makeTenancy(['rental_status' => 'Occupied']);
        $second = $this->competitor($t['reservation'], 'Under Negotiation');

        $this->assertNotNull($second->unitHeldByAnother());
        $this->assertFalse($second->advanceToPendingAgreement());
        $this->assertSame(self::REASON, $second->agreementBlockedReason());
    }

    public function test_the_only_reservation_on_a_unit_advances_as_before(): void
    {
        $t = $this->makeTenancy(['rental_status' => 'Under Negotiation']);
        $this->competitor($t['reservation'], 'Cancelled');

        $this->assertNull($t['reservation']->unitHeldByAnother(), 'a cancelled neighbour holds nothing');
        $this->assertTrue($t['reservation']->advanceToPendingAgreement());
        $this->assertSame('Pending Rental Agreement', $t['reservation']->fresh()->rental_status);
    }

    public function test_confirm_move_in_is_refused_when_another_reservation_is_occupied(): void
    {
        $t = $this->makeTenancy(['rental_status' => 'Rental Agreement Signed', 'agreed_at' => now()]);
        $held = $this->held($t['reservation']);
        $this->competitor($t['reservation'], 'Occupied');

        $this->assertFalse($t['reservation']->fresh()->confirmMoveIn());

        $this->assertSame('Rental Agreement Signed', $t['reservation']->fresh()->rental_status);
        $this->assertSame('Held', $held->fresh()->status, 'the deposit is not released');
    }

    public function test_mark_occupied_is_refused_when_another_reservation_is_occupied(): void
    {
        $t = $this->makeTenancy(['rental_status' => 'Rental Agreement Signed']);
        $this->competitor($t['reservation'], 'Occupied');

        $this->assertFalse($t['reservation']->fresh()->markOccupied());
        $this->assertSame('Rental Agreement Signed', $t['reservation']->fresh()->rental_status);
    }

    public function test_confirm_move_in_for_the_only_reservation_still_releases_the_deposit(): void
    {
        $t = $this->makeTenancy(['rental_status' => 'Rental Agreement Signed', 'agreed_at' => now()]);
        $held = $this->held($t['reservation']);

        $this->assertTrue($t['reservation']->fresh()->confirmMoveIn());

        $this->assertSame('Occupied', $t['reservation']->fresh()->rental_status);
        $this->assertSame('Released', $held->fresh()->status);
        $this->assertSame('Occupied', $t['reservation']->unit->fresh()->availability_status);
    }

    public function test_the_deadline_command_skips_a_unit_someone_else_occupies_and_keeps_the_money_held(): void
    {
        $t = $this->makeTenancy([
            'rental_status' => 'Rental Agreement Signed', 'agreed_at' => now(),
            'keys_turned_over_at' => now()->subDays(10), 'move_in_deadline_at' => now()->subDays(2),
        ]);
        $held = $this->held($t['reservation']);
        $this->competitor($t['reservation'], 'Occupied');

        Artisan::call('reservations:process-move-in-deadlines');

        $this->assertSame('Rental Agreement Signed', $t['reservation']->fresh()->rental_status);
        $this->assertSame('Held', $held->fresh()->status);
    }
}
