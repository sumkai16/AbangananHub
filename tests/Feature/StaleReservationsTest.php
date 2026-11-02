<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\Payment;
use App\Models\Reservation;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\TestCase;

/** reservations:process-stale: remind once, then cancel, when a reservation waits too long on a person. */
class StaleReservationsTest extends TestCase
{
    use CreatesMarketplaceFixtures, DatabaseTransactions;

    private $landlord;

    private $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        config(['rentals.stale_reservations.auto_cancel' => true]);
        $this->landlord = $this->makeLandlord();
        $this->tenant = $this->makeTenant();
    }

    /** A reservation in $status whose clock started $daysAgo days ago. */
    private function reservation(string $status, int $daysAgo): Reservation
    {
        $unit = $this->makeUnit($this->makeProperty($this->landlord));

        $reservation = Reservation::create([
            'tenant_id' => $this->tenant->user_id,
            'property_id' => $unit->property_id,
            'unit_id' => $unit->unit_id,
            'rental_status' => $status,
            'reservation_date' => today(),
            'target_move_in_date' => today()->addWeek(),
        ]);

        $then = now()->subDays($daysAgo)->subHour();
        DB::table('reservations')->where('reservation_id', $reservation->reservation_id)
            ->update(['created_at' => $then, 'updated_at' => $then]);

        return $reservation->fresh();
    }

    private function sweep(): void
    {
        $this->artisan('reservations:process-stale')->assertSuccessful();
    }

    private function notices(int $userId, string $title)
    {
        return Notification::where('user_id', $userId)->where('title', $title)->count();
    }

    public function test_a_fresh_reservation_is_left_alone(): void
    {
        foreach (['Inquiry', 'Under Negotiation', 'Pending Rental Agreement'] as $status) {
            $r = $this->reservation($status, 1);
            $this->sweep();
            $this->assertSame($status, $r->fresh()->rental_status);
            $this->assertNull($r->fresh()->stale_reminder_sent_at);
        }
    }

    public function test_an_unanswered_inquiry_reminds_the_landlord_once_then_cancels(): void
    {
        $r = $this->reservation('Inquiry', 3);

        $this->sweep();
        $this->assertSame('Inquiry', $r->fresh()->rental_status);
        $this->assertSame(1, $this->notices($this->landlord->user_id, 'Inquiry waiting for you'));
        $this->assertSame(0, $this->notices($this->tenant->user_id, 'Inquiry waiting for you'));

        // Running again the same day sends nothing more.
        $this->sweep();
        $this->assertSame(1, $this->notices($this->landlord->user_id, 'Inquiry waiting for you'));

        DB::table('reservations')->where('reservation_id', $r->reservation_id)->update(['created_at' => now()->subDays(8)]);
        $this->sweep();
        $this->assertSame('Cancelled', $r->fresh()->rental_status);
        $this->assertSame(1, $this->notices($this->tenant->user_id, 'Reservation closed'));
        $this->assertSame(1, $this->notices($this->landlord->user_id, 'Reservation closed'));
    }

    public function test_an_unsigned_agreement_reminds_the_tenant_then_frees_the_unit(): void
    {
        $r = $this->reservation('Pending Rental Agreement', 3);

        $this->sweep();
        $this->assertSame(1, $this->notices($this->tenant->user_id, 'Please sign your rental agreement'));
        $this->assertSame(1, $this->notices($this->landlord->user_id, 'Agreement still unsigned'));

        DB::table('reservations')->where('reservation_id', $r->reservation_id)->update(['updated_at' => now()->subDays(6)]);
        $this->sweep();

        $this->assertSame('Cancelled', $r->fresh()->rental_status);
        $this->assertSame('Available', $r->unit->fresh()->availability_status);
    }

    public function test_quiet_negotiation_reminds_both_then_cancels(): void
    {
        $r = $this->reservation('Under Negotiation', 6);

        $this->sweep();
        $this->assertSame('Under Negotiation', $r->fresh()->rental_status);
        $this->assertSame(1, $this->notices($this->landlord->user_id, 'Talks have gone quiet'));
        $this->assertSame(1, $this->notices($this->tenant->user_id, 'Talks have gone quiet'));

        DB::table('reservations')->where('reservation_id', $r->reservation_id)->update(['updated_at' => now()->subDays(15)]);
        $this->sweep();

        $this->assertSame('Cancelled', $r->fresh()->rental_status);
        $this->assertSame(1, $this->notices($this->tenant->user_id, 'Reservation closed'));
        $this->assertSame(1, $this->notices($this->landlord->user_id, 'Reservation closed'));
    }

    public function test_negotiation_clock_restarts_on_a_new_chat_message(): void
    {
        $r = $this->reservation('Under Negotiation', 20);
        $conversation = \App\Models\Conversation::create([
            'tenant_id' => $this->tenant->user_id,
            'landlord_id' => $this->landlord->user_id,
            'property_id' => $r->property_id,
        ]);
        DB::table('reservations')->where('reservation_id', $r->reservation_id)->update(['conversation_id' => $conversation->conversation_id]);

        \App\Models\Message::create([
            'conversation_id' => $conversation->conversation_id,
            'sender_id' => $this->tenant->user_id,
            'message' => 'Still interested!',
            'is_read' => false,
        ]);

        $this->sweep();
        $this->assertSame('Under Negotiation', $r->fresh()->rental_status);
        $this->assertNull($r->fresh()->stale_reminder_sent_at);
    }

    public function test_a_reservation_holding_money_is_never_cancelled(): void
    {
        $r = $this->reservation('Pending Rental Agreement', 30);
        Payment::create([
            'reservation_id' => $r->reservation_id,
            'payment_type' => 'Initial',
            'amount' => 5000,
            'status' => 'Held',
            'payment_method' => 'GCash',
        ]);

        $this->sweep();

        $this->assertSame('Pending Rental Agreement', $r->fresh()->rental_status);
    }

    public function test_with_auto_cancel_off_only_reminders_go_out(): void
    {
        config(['rentals.stale_reservations.auto_cancel' => false]);
        $r = $this->reservation('Inquiry', 30);

        $this->sweep();

        $this->assertSame('Inquiry', $r->fresh()->rental_status);
        $this->assertSame(1, $this->notices($this->landlord->user_id, 'Inquiry waiting for you'));
    }

    public function test_walk_in_tenants_are_skipped(): void
    {
        $this->tenant->forceFill(['is_walk_in' => true])->save();
        $r = $this->reservation('Pending Rental Agreement', 30);

        $this->sweep();

        $this->assertSame('Pending Rental Agreement', $r->fresh()->rental_status);
    }
}
