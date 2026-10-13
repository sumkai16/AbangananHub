<?php

namespace Tests\Feature\Payments;

use App\Models\Conversation;
use App\Models\Notification;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\Support\CreatesPaymentFixtures;
use Tests\TestCase;

/**
 * Three things end a reservation in "Occupied" with the deposit released: the
 * tenant confirming move-in, an admin releasing it, and the deadline timer.
 * Only the first is a confirmation. The "Move-in confirmed" notices (written by
 * ReservationObserver) must go out for that one alone, once each; the other two
 * send their own "deposit released" notices and must not add a second, untrue one.
 */
class MoveInNotificationsTest extends TestCase
{
    use CreatesMarketplaceFixtures, CreatesPaymentFixtures, DatabaseTransactions;

    /** @return array{landlord: User, tenant: User, reservation: Reservation} */
    private function escrowWithKeysHandedOver(array $reservation = []): array
    {
        $t = $this->makeTenancy(array_merge([
            'rental_status'       => 'Rental Agreement Signed',
            'keys_turned_over_at' => now()->subDay(),
            'move_in_deadline_at' => now()->addDays(5),
        ], $reservation));

        $conversation = Conversation::create([
            'tenant_id'   => $t['tenant']->user_id,
            'landlord_id' => $t['landlord']->user_id,
            'property_id' => $t['reservation']->property_id,
            'unit_id'     => $t['reservation']->unit_id,
            'status'      => 'Open',
        ]);
        $t['reservation']->update(['conversation_id' => $conversation->conversation_id]);

        $this->makePayment($t['reservation'], [
            'payment_type'   => 'Initial',
            'billing_period' => null,
            'payment_method' => 'GCash',
            'status'         => 'Held',
        ]);

        return $t;
    }

    private function titles(User $user): array
    {
        return Notification::where('user_id', $user->user_id)->pluck('title')->all();
    }

    private function confirmedNotices(User $user): int
    {
        return Notification::where('user_id', $user->user_id)->where('title', 'like', 'Move-in confirmed%')->count();
    }

    public function test_when_the_tenant_confirms_move_in_both_sides_get_the_confirmation_once(): void
    {
        $t = $this->escrowWithKeysHandedOver();

        $this->actingAs($t['tenant'])->post(route('agreements.confirmMoveIn', $t['reservation']))->assertRedirect();

        $this->assertSame('Occupied', $t['reservation']->fresh()->rental_status);
        $this->assertSame(['Move-in confirmed — payment released'], array_values(array_filter($this->titles($t['landlord']), fn ($title) => str_starts_with($title, 'Move-in confirmed'))));
        $this->assertSame(['Move-in confirmed'], array_values(array_filter($this->titles($t['tenant']), fn ($title) => str_starts_with($title, 'Move-in confirmed'))));
    }

    public function test_an_admin_release_does_not_claim_the_tenant_confirmed(): void
    {
        $t = $this->escrowWithKeysHandedOver(['move_in_disputed_at' => now()->subDay(), 'move_in_dispute_reason' => 'Keys never worked']);
        $admin = $this->makeUserWithRole('Admin');
        $payment = $t['reservation']->payments()->where('status', 'Held')->firstOrFail();

        $this->actingAs($admin)->post(route('admin.payments.release', $payment))->assertRedirect();

        $this->assertSame('Occupied', $t['reservation']->fresh()->rental_status);
        $this->assertNull($t['reservation']->fresh()->tenant_confirmed_move_in_at);
        $this->assertSame(0, $this->confirmedNotices($t['landlord']));
        $this->assertSame(0, $this->confirmedNotices($t['tenant']));
        $this->assertSame(1, Notification::where('user_id', $t['landlord']->user_id)->where('type', 'payment_released')->count(), 'The admin release sends its own single notice.');
    }

    public function test_the_deadline_timer_does_not_claim_the_tenant_confirmed(): void
    {
        $t = $this->escrowWithKeysHandedOver(['move_in_deadline_at' => now()->subDays(2)]);

        $this->artisan('reservations:process-move-in-deadlines')->assertExitCode(0);

        $this->assertSame('Occupied', $t['reservation']->fresh()->rental_status);
        $this->assertSame(0, $this->confirmedNotices($t['landlord']));
        $this->assertSame(0, $this->confirmedNotices($t['tenant']));
        $this->assertSame(1, Notification::where('user_id', $t['landlord']->user_id)->where('type', 'payment_released')->count());
    }
}
