<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\TestCase;

/** The alert rows on the landlord dashboard: each names its listing and opens the page that answers it. */
class LandlordDashboardAlertsTest extends TestCase
{
    use CreatesMarketplaceFixtures, DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        config(['broadcasting.default' => 'null']);
    }

    public function test_one_rejected_listing_opens_that_listing_and_says_what_to_do(): void
    {
        $landlord = $this->makeLandlord();
        $p = $this->makeProperty($landlord, ['title' => 'Tisa Garden Rooms', 'verification_status' => 'Rejected']);

        $this->actingAs($landlord)->get(route('landlord.dashboard'))->assertOk()
            ->assertSee('Tisa Garden Rooms was rejected')->assertSee('Fix and resubmit')
            ->assertSee(route('landlord.properties.show', $p), false)
            ->assertSee('Fix now');
    }

    public function test_several_rejected_listings_open_the_rejected_filter(): void
    {
        $landlord = $this->makeLandlord();
        $this->makeProperty($landlord, ['verification_status' => 'Rejected']);
        $this->makeProperty($landlord, ['verification_status' => 'Rejected']);

        $this->actingAs($landlord)->get(route('landlord.dashboard'))->assertOk()
            ->assertSee('2 listings were rejected')
            ->assertSee(route('landlord.properties.index', ['status' => 'Rejected']), false);
    }

    public function test_a_suspended_listing_and_an_unfinished_one_open_the_right_pages(): void
    {
        $landlord = $this->makeLandlord();
        $suspended = $this->makeProperty($landlord, ['title' => 'Mambaling Annex', 'publication_status' => 'Suspended']);
        $draft = $this->makeProperty($landlord, ['title' => 'Half Done House', 'publication_status' => 'Draft']);

        $this->actingAs($landlord)->get(route('landlord.dashboard'))->assertOk()
            ->assertSee('Mambaling Annex is suspended')
            ->assertSee(route('landlord.properties.show', $suspended), false)
            ->assertSee('Half Done House is not finished')
            ->assertSee(route('properties.wizard.resume', $draft), false);
    }

    public function test_the_properties_list_filters_by_suspended_and_by_not_finished(): void
    {
        $landlord = $this->makeLandlord();
        $this->makeProperty($landlord, ['title' => 'Live One']);
        $this->makeProperty($landlord, ['title' => 'Suspended One', 'publication_status' => 'Suspended']);
        $this->makeProperty($landlord, ['title' => 'Draft One', 'publication_status' => 'Draft']);

        $this->actingAs($landlord)->get(route('landlord.properties.index', ['status' => 'Suspended']))->assertOk()
            ->assertSee('Suspended One')->assertDontSee('Live One')->assertDontSee('Draft One');
        $this->actingAs($landlord)->get(route('landlord.properties.index', ['status' => 'Draft']))->assertOk()
            ->assertSee('Draft One')->assertDontSee('Live One')->assertDontSee('Suspended One');
    }

    public function test_a_listing_waiting_for_the_admin_is_a_quiet_row_in_its_own_group(): void
    {
        $landlord = $this->makeLandlord();
        $p = $this->makeProperty($landlord, ['title' => 'Lahug Garden Studios', 'verification_status' => 'Pending']);

        $this->actingAs($landlord)->get(route('landlord.dashboard'))->assertOk()
            ->assertSee('Waiting on admin')
            ->assertSee('Lahug Garden Studios is waiting for admin approval')
            ->assertSee(route('landlord.properties.show', $p), false);
    }
}
