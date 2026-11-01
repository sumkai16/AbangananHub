<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\LandlordBlockedDate;
use App\Models\LandlordViewingHour;
use App\Models\Property;
use App\Models\Reservation;
use App\Models\User;
use App\Models\ViewingRequest;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\TestCase;

/**
 * The open-slot list behind the tenant's day and time chips (ViewingController::slots).
 * Builds its own landlord, tenant and property in a rolled-back transaction.
 */
class ViewingSlotsTest extends TestCase
{
    use CreatesMarketplaceFixtures, DatabaseTransactions;

    private User $landlord;
    private User $tenant;
    private Property $property;
    private Reservation $reservation;

    protected function setUp(): void
    {
        parent::setUp();

        config(['broadcasting.default' => 'null']);

        $this->landlord = $this->makeLandlord();
        $this->tenant = $this->makeTenant();
        $this->property = $this->makeProperty($this->landlord);

        $conversation = Conversation::create([
            'tenant_id'   => $this->tenant->user_id,
            'landlord_id' => $this->landlord->user_id,
            'property_id' => $this->property->property_id,
        ]);

        $this->reservation = Reservation::create([
            'tenant_id'        => $this->tenant->user_id,
            'property_id'      => $this->property->property_id,
            'conversation_id'  => $conversation->conversation_id,
            'rental_status'    => 'Under Negotiation',
            'reservation_date' => today(),
        ]);
    }

    private function slots(?User $as = null)
    {
        return $this->actingAs($as ?? $this->tenant)->getJson(route('viewings.slots', $this->reservation));
    }

    private function allSlots(array $days): array
    {
        return collect($days)->flatMap(fn ($d) => $d['slots'])->all();
    }

    public function test_lists_open_days_in_the_expected_shape(): void
    {
        $days = $this->slots()->assertOk()->json('days');

        $this->assertNotEmpty($days);
        $this->assertLessThanOrEqual(7, count($days));
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $days[0]['date']);
        $this->assertNotEmpty($days[0]['label']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:00$/', $days[0]['slots'][0]['at']);
        $this->assertArrayHasKey('open', $days[0]['slots'][0]);
    }

    public function test_every_open_slot_passes_the_same_rules_as_a_request(): void
    {
        $days = $this->slots()->json('days');

        foreach ($this->allSlots($days) as $slot) {
            if ($slot['open']) {
                $this->assertNull(ViewingRequest::slotProblem(\Illuminate\Support\Carbon::parse($slot['at']), $this->landlord->user_id));
            }
        }
    }

    public function test_respects_weekly_hours_and_days_off(): void
    {
        $target = today()->addDays(3);
        // Only the target weekday, 9 AM to 11 AM: starts at 9 and 10.
        LandlordViewingHour::create([
            'landlord_id' => $this->landlord->user_id,
            'day_of_week' => $target->dayOfWeek,
            'start_hour'  => 9,
            'end_hour'    => 11,
        ]);

        $days = $this->slots()->json('days');

        $this->assertNotEmpty($days);
        foreach ($days as $day) {
            $this->assertSame($target->dayOfWeek, \Illuminate\Support\Carbon::parse($day['date'])->dayOfWeek);
            $this->assertSame(['09:00', '10:00'], collect($day['slots'])->map(fn ($s) => substr($s['at'], 11))->all());
        }
    }

    public function test_blocked_days_are_left_out(): void
    {
        $blocked = today()->addDay();
        LandlordBlockedDate::create(['landlord_id' => $this->landlord->user_id, 'date' => $blocked->toDateString()]);

        $dates = collect($this->slots()->json('days'))->pluck('date');

        $this->assertNotContains($blocked->toDateString(), $dates->all());
    }

    public function test_a_slot_held_by_another_viewing_is_closed(): void
    {
        $held = today()->addDays(2)->setTime(10, 0);
        ViewingRequest::create([
            'source'       => 'Offline',
            'property_id'  => $this->property->property_id,
            'landlord_id'  => $this->landlord->user_id,
            'visitor_name' => 'Walk-in visitor',
            'proposed_by'  => $this->landlord->user_id,
            'scheduled_at' => $held,
            'status'       => 'Confirmed',
        ]);

        $slot = collect($this->allSlots($this->slots()->json('days')))->firstWhere('at', $held->format('Y-m-d\TH:i'));

        $this->assertNotNull($slot);
        $this->assertFalse($slot['open']);
    }

    public function test_only_the_reservations_tenant_may_read_slots(): void
    {
        $this->slots($this->landlord)->assertForbidden();
        $this->slots($this->makeTenant())->assertForbidden();
    }

    public function test_slots_are_refused_once_viewing_is_no_longer_allowed(): void
    {
        $this->reservation->update(['rental_status' => 'Occupied']);

        $this->slots()->assertForbidden();
    }

    public function test_tenant_card_renders_the_chip_picker_and_landlord_card_keeps_the_calendar(): void
    {
        $this->actingAs($this->tenant)
            ->get(route('conversations.index', ['active' => $this->reservation->conversation_id]))
            ->assertOk();

        $html = view('conversations.partials._viewing-card', [
            'reservation' => $this->reservation->fresh(['property', 'viewings']),
            'isLandlord'  => false,
            'otherParty'  => $this->landlord,
        ])->render();

        $this->assertStringContainsString('Pick another time', $html);
    }
}
