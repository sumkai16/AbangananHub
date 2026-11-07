<?php

namespace Tests\Feature;

use App\Models\Message;
use App\Models\Reservation;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\TestCase;

/** Reservation::startInquiry: one open inquiry per tenant and unit, written together or not at all. */
class StartInquiryTest extends TestCase
{
    use CreatesMarketplaceFixtures, DatabaseTransactions;

    private function fields(): array
    {
        return ['target_move_in_date' => today()->addWeek(), 'target_move_out_date' => null, 'occupants_count' => 1];
    }

    public function test_it_creates_the_reservation_the_chat_and_the_first_message_together(): void
    {
        $tenant = $this->makeTenant();
        $unit = $this->makeUnit($this->makeProperty($this->makeLandlord()));

        $reservation = Reservation::startInquiry($unit, $tenant->user_id, $this->fields(), 'Hello');

        $this->assertSame('Inquiry', $reservation->rental_status);
        $this->assertNotNull($reservation->conversation_id);
        $messages = Message::where('conversation_id', $reservation->conversation_id)->get();
        $this->assertCount(1, $messages);
        $this->assertSame('inquiry_summary', $messages->first()->kind);
        $this->assertSame('Hello', $messages->first()->message);
    }

    public function test_a_second_tap_creates_nothing_more(): void
    {
        $tenant = $this->makeTenant();
        $unit = $this->makeUnit($this->makeProperty($this->makeLandlord()));

        $first = Reservation::startInquiry($unit, $tenant->user_id, $this->fields(), '');
        $second = Reservation::startInquiry($unit, $tenant->user_id, $this->fields(), '');

        $this->assertNotNull($first);
        $this->assertNull($second);
        $this->assertSame(1, Reservation::where('unit_id', $unit->unit_id)->where('tenant_id', $tenant->user_id)->count());
        $this->assertSame(1, Message::where('conversation_id', $first->conversation_id)->count());
    }

    public function test_a_closed_inquiry_does_not_block_a_new_one(): void
    {
        $tenant = $this->makeTenant();
        $unit = $this->makeUnit($this->makeProperty($this->makeLandlord()));

        $first = Reservation::startInquiry($unit, $tenant->user_id, $this->fields(), '');
        $first->cancel();

        $this->assertNotNull(Reservation::startInquiry($unit, $tenant->user_id, $this->fields(), ''));
    }
}
