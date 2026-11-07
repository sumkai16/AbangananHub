<?php

namespace Tests\Feature;

use App\Http\Controllers\Landlord\ReservationController;
use App\Models\Notification;
use App\Models\Reservation;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\TestCase;

/** The landlord's Reject dialog sends a quick reason and a note; the tenant sees both. */
class RejectReasonTest extends TestCase
{
    use CreatesMarketplaceFixtures, DatabaseTransactions;

    private $landlord;

    private $tenant;

    private Reservation $reservation;

    protected function setUp(): void
    {
        parent::setUp();
        $this->landlord = $this->makeLandlord();
        $this->tenant = $this->makeTenant();
        $unit = $this->makeUnit($this->makeProperty($this->landlord));

        $this->reservation = Reservation::startInquiry($unit, $this->tenant->user_id, [
            'target_move_in_date' => today()->addWeek(), 'target_move_out_date' => null, 'occupants_count' => 1,
        ], '');
    }

    private function reject(array $payload)
    {
        return $this->actingAs($this->landlord)
            ->patch(route('landlord.reservations.reject', $this->reservation), $payload);
    }

    public function test_the_picked_reason_and_note_reach_the_tenant(): void
    {
        $this->reject(['reason_preset' => ReservationController::REJECT_REASONS[0], 'reason_note' => 'Rented it to a friend.'])
            ->assertSessionHas('success');

        $r = $this->reservation->fresh();
        $this->assertSame('Rejected', $r->rental_status);
        $this->assertSame('The unit is no longer available. Rented it to a friend.', $r->rejection_reason);

        $notice = Notification::where('user_id', $this->tenant->user_id)->where('title', 'Inquiry declined')->first();
        $this->assertStringContainsString('Rented it to a friend.', $notice->message);

        $this->actingAs($this->tenant)->get(route('reservations.index'))
            ->assertOk()
            ->assertSee('Reason: The unit is no longer available. Rented it to a friend.');
    }

    public function test_a_reason_is_optional(): void
    {
        $this->reject([])->assertSessionHas('success');

        $this->assertSame('Rejected', $this->reservation->fresh()->rental_status);
        $this->assertNull($this->reservation->fresh()->rejection_reason);
    }

    public function test_a_made_up_quick_reason_is_refused(): void
    {
        $this->reject(['reason_preset' => 'Because I said so'])->assertSessionHasErrors('reason_preset');

        $this->assertSame('Inquiry', $this->reservation->fresh()->rental_status);
    }

    public function test_the_mobile_apps_free_text_reason_still_works(): void
    {
        $this->reject(['rejection_reason' => 'Sorry, not this month.'])->assertSessionHas('success');

        $this->assertSame('Sorry, not this month.', $this->reservation->fresh()->rejection_reason);
    }
}
