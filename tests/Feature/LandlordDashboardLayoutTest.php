<?php

namespace Tests\Feature;

use App\Models\Notification;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\TestCase;

/**
 * The dashboard is one navy band of numbers over one tabbed card (attention, notifications, viewings) with the
 * properties beside it, instead of a tall stack of separate sections.
 */
class LandlordDashboardLayoutTest extends TestCase
{
    use CreatesMarketplaceFixtures, DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        config(['broadcasting.default' => 'null']);
    }

    public function test_the_numbers_sit_in_one_band_above_two_columns_of_cards(): void
    {
        $landlord = $this->makeLandlord();
        $this->makeUnit($this->makeProperty($landlord));

        $this->actingAs($landlord)->get(route('landlord.dashboard'))
            ->assertOk()
            ->assertSee('Rent this month')
            ->assertSee('Overdue')
            ->assertSee('Occupancy')
            ->assertSee('Vacant units')
            ->assertSee('Open inquiries')
            ->assertSee('Needs your attention')
            ->assertSee('Notifications')
            ->assertSee('Your properties')
            ->assertSee('Viewings');
    }

    public function test_the_unread_count_shows_next_to_notifications(): void
    {
        $landlord = $this->makeLandlord();
        Notification::notify($landlord->user_id, 'review', 'New review', 'Five stars.', '/landlord/reviews');

        $this->actingAs($landlord)->get(route('landlord.dashboard'))
            ->assertOk()
            ->assertSee('1 new');
    }

    public function test_nothing_waiting_shows_the_all_caught_up_line(): void
    {
        $landlord = $this->makeLandlord();

        $this->actingAs($landlord)->get(route('landlord.dashboard'))
            ->assertOk()
            ->assertSee("You're all caught up.", false);
    }

    public function test_a_listing_waiting_for_approval_shows_as_a_status_row_with_a_button(): void
    {
        $landlord = $this->makeLandlord();
        $this->makeProperty($landlord, ['verification_status' => 'Pending']);

        $this->actingAs($landlord)->get(route('landlord.dashboard'))
            ->assertOk()
            ->assertSee('aria-label="Setup alerts"', false)
            ->assertSee('waiting for admin approval.');
    }
}
