<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\LandlordBlockedDate;
use App\Models\Property;
use App\Models\Reservation;
use App\Models\User;
use App\Models\ViewingRequest;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\TestCase;

/**
 * Unit viewings: online requests from the chat and offline ones the landlord
 * logs. See plans/unit-viewing-scheduling.md.
 *
 * Builds its own landlord, tenant and property, inside a transaction that is
 * rolled back, so it does not depend on seeded data.
 */
class ViewingSchedulingTest extends TestCase
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

    /** $daysAhead days from today, on the hour. */
    private function slot(int $daysAhead = 1, int $hour = 10): Carbon
    {
        return today()->addDays($daysAhead)->setTime($hour, 0);
    }

    private function request(User $as, ?Carbon $slot = null)
    {
        return $this->actingAs($as)->post(route('viewings.store', $this->reservation), [
            'scheduled_at' => ($slot ?? $this->slot())->format('Y-m-d\TH:i'),
        ]);
    }

    private function latest(): ViewingRequest
    {
        return ViewingRequest::where('reservation_id', $this->reservation->reservation_id)->latest('viewing_id')->firstOrFail();
    }

    public function test_tenant_requests_a_viewing(): void
    {
        $this->request($this->tenant)->assertSessionHas('success');

        $viewing = $this->latest();
        $this->assertSame('Pending', $viewing->status);
        $this->assertSame('Online', $viewing->source);
        $this->assertSame($this->tenant->user_id, $viewing->proposed_by);
        $this->assertTrue($viewing->awaitsResponseFrom($this->landlord->user_id));
    }

    public function test_second_open_viewing_is_refused(): void
    {
        $this->request($this->tenant);
        $this->request($this->tenant, $this->slot(2))->assertSessionHas('error');

        $this->assertSame(1, $this->reservation->viewings()->count());
    }

    public function test_only_the_reservations_tenant_can_request(): void
    {
        $this->request($this->landlord)->assertForbidden();

        $this->request($this->makeTenant())->assertForbidden();
    }

    public function test_not_allowed_before_the_landlord_accepts(): void
    {
        $this->reservation->update(['rental_status' => 'Inquiry']);

        $this->request($this->tenant)->assertForbidden();
    }

    public function test_slot_rules(): void
    {
        $this->request($this->tenant, now()->subHour()->startOfHour())->assertSessionHas('error');
        $this->request($this->tenant, $this->slot(1, 20))->assertSessionHas('error');
        $this->request($this->tenant, $this->slot(1, 10)->setMinute(30))->assertSessionHas('error');
        $this->request($this->tenant, $this->slot(90))->assertSessionHas('error');

        LandlordBlockedDate::create(['landlord_id' => $this->landlord->user_id, 'date' => $this->slot(3)->toDateString()]);
        $this->request($this->tenant, $this->slot(3))->assertSessionHas('error');

        $this->assertSame(0, $this->reservation->viewings()->count());
    }

    public function test_proposer_cannot_confirm_own_time(): void
    {
        $this->request($this->tenant);

        $this->actingAs($this->tenant)->post(route('viewings.confirm', $this->latest()))->assertForbidden();
    }

    public function test_landlord_approves(): void
    {
        $this->request($this->tenant);

        $this->actingAs($this->landlord)->post(route('viewings.confirm', $this->latest()))->assertSessionHas('success');

        $this->assertSame('Confirmed', $this->latest()->status);
    }

    public function test_reschedule_flips_who_confirms(): void
    {
        $this->request($this->tenant);
        $viewing = $this->latest();

        $this->actingAs($this->landlord)->post(route('viewings.reschedule', $viewing), [
            'scheduled_at' => $this->slot(2, 14)->format('Y-m-d\TH:i'),
        ])->assertSessionHas('success');

        $viewing->refresh();
        $this->assertSame('Pending', $viewing->status);
        $this->assertSame($this->landlord->user_id, $viewing->proposed_by);
        $this->assertTrue($viewing->awaitsResponseFrom($this->tenant->user_id));

        $this->actingAs($this->tenant)->post(route('viewings.confirm', $viewing))->assertSessionHas('success');
        $this->assertSame('Confirmed', $viewing->fresh()->status);
    }

    public function test_decline_then_request_again(): void
    {
        $this->request($this->tenant);

        $this->actingAs($this->landlord)->post(route('viewings.decline', $this->latest()), ['decline_reason' => 'Out of town'])
            ->assertSessionHas('success');
        $this->assertSame('Declined', $this->latest()->status);

        $this->request($this->tenant, $this->slot(4))->assertSessionHas('success');
        $this->assertSame(2, $this->reservation->viewings()->count());
    }

    public function test_either_party_cancels(): void
    {
        $this->request($this->tenant);

        $this->actingAs($this->landlord)->post(route('viewings.cancel', $this->latest()))->assertSessionHas('success');

        $this->assertSame('Cancelled', $this->latest()->status);
    }

    public function test_ending_the_reservation_cancels_its_viewings(): void
    {
        $this->request($this->tenant);

        $this->reservation->refresh()->reject('No longer available');

        $this->assertSame('Cancelled', $this->latest()->status);
    }

    public function test_landlord_adds_an_offline_viewing(): void
    {
        $slot = $this->slot(2, 9);

        $this->actingAs($this->landlord)->post(route('landlord.viewings.store'), [
            'visitor_name'  => 'Juan Dela Cruz',
            'visitor_phone' => '0917 123 4567',
            'property_id'   => $this->property->property_id,
            'scheduled_at'  => $slot->format('Y-m-d\TH:i'),
        ])->assertSessionHas('success');

        $viewing = ViewingRequest::where('landlord_id', $this->landlord->user_id)->where('source', 'Offline')->latest('viewing_id')->firstOrFail();
        $this->assertSame('Confirmed', $viewing->status);
        $this->assertNull($viewing->tenant_id);
        $this->assertNull($viewing->reservation_id);
        $this->assertSame('Juan Dela Cruz', $viewing->visitorName());
        $this->assertTrue($viewing->scheduled_at->equalTo($slot));
    }

    public function test_offline_viewing_on_another_landlords_property_is_refused(): void
    {
        $foreign = $this->makeProperty($this->makeLandlord());

        $this->actingAs($this->landlord)->post(route('landlord.viewings.store'), [
            'visitor_name' => 'Someone',
            'property_id'  => $foreign->property_id,
            'scheduled_at' => $this->slot()->format('Y-m-d\TH:i'),
        ])->assertForbidden();
    }

    public function test_landlord_reschedules_and_cancels_an_offline_viewing(): void
    {
        $viewing = ViewingRequest::create([
            'source' => 'Offline', 'property_id' => $this->property->property_id,
            'visitor_name' => 'Ana', 'landlord_id' => $this->landlord->user_id,
            'scheduled_at' => $this->slot(), 'status' => 'Confirmed', 'proposed_by' => $this->landlord->user_id,
        ]);

        $this->actingAs($this->landlord)->patch(route('landlord.viewings.update', $viewing), [
            'visitor_name' => 'Ana Reyes',
            'property_id'  => $this->property->property_id,
            'scheduled_at' => $this->slot(3, 15)->format('Y-m-d\TH:i'),
        ])->assertSessionHas('success');

        $viewing->refresh();
        $this->assertSame('Ana Reyes', $viewing->visitor_name);
        $this->assertSame(15, $viewing->scheduled_at->hour);
        $this->assertSame('Confirmed', $viewing->status);

        $this->actingAs($this->landlord)->delete(route('landlord.viewings.destroy', $viewing))->assertSessionHas('success');
        $this->assertSame('Cancelled', $viewing->fresh()->status);
    }

    public function test_offline_viewing_is_not_reachable_from_the_chat_routes(): void
    {
        $viewing = ViewingRequest::create([
            'source' => 'Offline', 'property_id' => $this->property->property_id,
            'visitor_name' => 'Ana', 'landlord_id' => $this->landlord->user_id,
            'scheduled_at' => $this->slot(), 'status' => 'Confirmed', 'proposed_by' => $this->landlord->user_id,
        ]);

        $this->actingAs($this->tenant)->post(route('viewings.cancel', $viewing))->assertForbidden();
        $this->actingAs($this->landlord)->post(route('viewings.confirm', $viewing))->assertForbidden();
    }

    public function test_blocking_a_day_with_viewings_is_refused(): void
    {
        $this->request($this->tenant, $this->slot(5));

        $this->actingAs($this->landlord)->post(route('landlord.viewings.block'), ['date' => $this->slot(5)->toDateString()])
            ->assertSessionHas('error');
        $this->assertFalse(LandlordBlockedDate::where('landlord_id', $this->landlord->user_id)->whereDate('date', $this->slot(5))->exists());

        $this->actingAs($this->landlord)->post(route('landlord.viewings.block'), ['date' => $this->slot(6)->toDateString()])
            ->assertSessionHas('success');
        $this->assertTrue(LandlordBlockedDate::where('landlord_id', $this->landlord->user_id)->whereDate('date', $this->slot(6))->exists());
    }

    public function test_viewings_tab_renders(): void
    {
        $this->request($this->tenant);

        $this->actingAs($this->landlord)->get(route('landlord.viewings.index'))
            ->assertOk()
            ->assertSee('Upcoming viewings')
            ->assertSee('Needs your answer');

        $this->actingAs($this->landlord)->get(route('landlord.reservations.index'))
            ->assertOk()
            ->assertSee('Viewings');
    }

    public function test_chat_panel_shows_the_viewing_card(): void
    {
        $this->actingAs($this->tenant)
            ->get(route('conversations.show', $this->reservation->conversation_id), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()
            ->assertSee('Schedule a viewing');
    }

    /** Save hours for one weekday only: 9 AM until 12 PM (last start 11 AM). */
    private function openOnlyOn(Carbon $day): void
    {
        $this->actingAs($this->landlord)->put(route('landlord.viewings.hours'), [
            'days' => [$day->dayOfWeek => ['on' => '1', 'start' => 9, 'end' => 12]],
        ])->assertSessionHas('success');
    }

    public function test_weekly_hours_limit_tenant_requests(): void
    {
        $day = $this->slot(2);
        $this->openOnlyOn($day);

        // Another weekday: closed.
        $this->request($this->tenant, $this->slot(3, 10))->assertSessionHas('error');
        // Right day, outside the hours (12 PM is the end, not a start).
        $this->request($this->tenant, $day->copy()->setTime(12, 0))->assertSessionHas('error');
        $this->request($this->tenant, $day->copy()->setTime(8, 0))->assertSessionHas('error');
        $this->assertSame(0, $this->reservation->viewings()->count());

        // Inside.
        $this->request($this->tenant, $day->copy()->setTime(11, 0))->assertSessionHas('success');
    }

    public function test_weekly_hours_limit_reschedules_too(): void
    {
        $this->request($this->tenant, $this->slot(2, 10));
        $this->openOnlyOn($this->slot(2));

        $this->actingAs($this->landlord)->post(route('viewings.reschedule', $this->latest()), [
            'scheduled_at' => $this->slot(3, 10)->format('Y-m-d\TH:i'),
        ])->assertSessionHas('error');
    }

    public function test_offline_viewings_ignore_weekly_hours(): void
    {
        $this->openOnlyOn($this->slot(2));

        $this->actingAs($this->landlord)->post(route('landlord.viewings.store'), [
            'visitor_name' => 'Walk-in',
            'property_id'  => $this->property->property_id,
            'scheduled_at' => $this->slot(3, 15)->format('Y-m-d\TH:i'),
        ])->assertSessionHas('success');
    }

    public function test_hours_form_rejects_bad_input(): void
    {
        $this->actingAs($this->landlord)->put(route('landlord.viewings.hours'), [
            'days' => [1 => ['start' => 9, 'end' => 12]],
        ])->assertSessionHas('error');

        $this->actingAs($this->landlord)->put(route('landlord.viewings.hours'), [
            'days' => [1 => ['on' => '1', 'start' => 14, 'end' => 10]],
        ])->assertSessionHas('error');

        $this->assertFalse(\App\Models\LandlordViewingHour::where('landlord_id', $this->landlord->user_id)->exists());
    }

    public function test_no_hours_set_means_the_default_every_day(): void
    {
        $this->assertSame(\App\Models\LandlordViewingHour::defaultWeek(), \App\Models\LandlordViewingHour::weekFor($this->landlord->user_id));
        $this->request($this->tenant, $this->slot(1, 17))->assertSessionHas('success');
    }
}
