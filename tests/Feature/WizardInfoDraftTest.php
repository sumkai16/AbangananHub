<?php

namespace Tests\Feature;

use App\Models\PropertyWizardDraft;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\TestCase;

/** Step 1 of the property wizard is saved in the database, so it survives an expired session. */
class WizardInfoDraftTest extends TestCase
{
    use CreatesMarketplaceFixtures, DatabaseTransactions;

    public function test_info_survives_a_new_session_and_shows_on_the_properties_page(): void
    {
        $landlord = $this->makeLandlord();

        $this->actingAs($landlord)->post(route('properties.wizard.info.store'), [
            'title' => 'Sunrise Boarding House', 'property_type' => 'Boarding House',
            'living_arrangement' => 'Mixed', 'occupancy_preference' => 'No Preference',
            'description' => 'A quiet boarding house near the university.', 'number_of_units' => 4,
        ])->assertRedirect(route('properties.wizard.location.create'));

        $this->assertDatabaseHas('property_wizard_drafts', ['landlord_id' => $landlord->user_id]);

        // A brand-new session: nothing in it, the draft is still found.
        $this->flushSession();
        $this->actingAs($landlord)->get(route('properties.wizard.location.create'))->assertOk();
        $this->actingAs($landlord)->get(route('properties.create'))->assertOk()->assertSee('Sunrise Boarding House');
        $this->actingAs($landlord)->get(route('landlord.properties.index'))->assertOk()->assertSee('You have an unfinished property');
    }

    public function test_no_draft_means_no_banner_and_location_redirects_to_step_one(): void
    {
        $landlord = $this->makeLandlord();
        PropertyWizardDraft::where('landlord_id', $landlord->user_id)->delete();

        $this->actingAs($landlord)->get(route('landlord.properties.index'))->assertDontSee('You have an unfinished property');
        $this->actingAs($landlord)->get(route('properties.wizard.location.create'))->assertRedirect(route('properties.create'));
    }
}
