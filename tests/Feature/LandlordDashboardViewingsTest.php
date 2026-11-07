<?php

namespace Tests\Feature;

use App\Models\Reservation;
use App\Models\ViewingRequest;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\TestCase;

/**
 * The landlord dashboard's Viewings card: the next two weeks on a date strip, each day's viewings with a status,
 * and a count of the ones waiting on the landlord's answer.
 */
class LandlordDashboardViewingsTest extends TestCase
{
    use CreatesMarketplaceFixtures, DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        config(['broadcasting.default' => 'null']);
        Carbon::setTestNow('2026-11-02 08:00:00');
    }

    private function viewing(array $attributes): ViewingRequest
    {
        return ViewingRequest::create($attributes + ['status' => 'Confirmed']);
    }

    public function test_the_card_lists_the_next_two_weeks_with_statuses(): void
    {
        $landlord = $this->makeLandlord();
        $tenant = $this->makeTenant();
        $tenant->forceFill(['first_name' => 'Kirby', 'last_name' => 'Lane'])->save();
        $unit = $this->makeUnit($this->makeProperty($landlord));
        $reservation = Reservation::create([
            'tenant_id' => $tenant->user_id, 'property_id' => $unit->property_id, 'unit_id' => $unit->unit_id,
            'rental_status' => 'Inquiry', 'reservation_date' => '2026-11-01',
        ]);
        $base = ['landlord_id' => $landlord->user_id, 'property_id' => $unit->property_id, 'unit_id' => $unit->unit_id];

        $this->viewing($base + ['source' => 'Offline', 'visitor_name' => 'Ana Reyes', 'scheduled_at' => '2026-11-03 10:00:00', 'proposed_by' => $landlord->user_id]);
        // The tenant asked; the landlord has to answer.
        $this->viewing($base + ['source' => 'Online', 'tenant_id' => $tenant->user_id, 'reservation_id' => $reservation->reservation_id,
            'scheduled_at' => '2026-11-03 14:30:00', 'status' => 'Pending', 'proposed_by' => $tenant->user_id]);
        // After the three months the calendar holds, and a declined one: neither shows.
        $this->viewing($base + ['source' => 'Offline', 'visitor_name' => 'Far Future', 'scheduled_at' => '2027-02-01 10:00:00', 'proposed_by' => $landlord->user_id]);
        $this->viewing($base + ['source' => 'Offline', 'visitor_name' => 'Called Off', 'scheduled_at' => '2026-11-04 10:00:00', 'status' => 'Declined', 'proposed_by' => $landlord->user_id]);

        $html = $this->actingAs($landlord)->get(route('landlord.dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('id="dash-viewings-title"', $html);
        $this->assertStringContainsString('1 to answer', $html);
        $this->assertStringContainsString('Ana Reyes', $html);
        $this->assertStringContainsString('Kirby Lane', $html);
        $this->assertStringContainsString('Needs your answer', $html);
        $this->assertStringContainsString('10:00 AM', $html);
        $this->assertStringNotContainsString('Far Future', $html);
        $this->assertStringNotContainsString('Called Off', $html);
        // The strip opens on the first day that has a viewing, and rows open the full calendar at that month.
        $this->assertStringContainsString("day: '2026-11-03'", $html);
        $this->assertStringContainsString(route('landlord.viewings.index', ['month' => '2026-11']), $html);
        // The calendar: November to January, with the needs-an-answer day marked amber.
        $this->assertStringContainsString('November 2026', $html);
        $this->assertStringContainsString('January 2027', $html);
        $this->assertStringNotContainsString('February 2027', $html);
        $this->assertStringContainsString('Next: Tomorrow · 10:00 AM · Ana Reyes', $html);
    }

    public function test_no_viewings_shows_a_calm_empty_line_and_no_other_landlords_viewings(): void
    {
        $landlord = $this->makeLandlord();
        $other = $this->makeLandlord();
        $unit = $this->makeUnit($this->makeProperty($other));
        $this->viewing(['landlord_id' => $other->user_id, 'property_id' => $unit->property_id, 'unit_id' => $unit->unit_id,
            'source' => 'Offline', 'visitor_name' => 'Someone Else', 'scheduled_at' => '2026-11-03 10:00:00', 'proposed_by' => $other->user_id]);

        $this->actingAs($landlord)->get(route('landlord.dashboard'))->assertOk()
            ->assertSee('No viewings booked yet.')
            ->assertSee('Nothing booked yet.')
            ->assertDontSee('Someone Else');
    }
}
