<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\TestCase;

/**
 * Once a listing is submitted for review the add-property wizard no longer applies. A landlord who reaches a wizard
 * page anyway (the browser Back button after "Submit for review", an old tab) is sent to the property page with a
 * note, not shown a bare 409.
 */
class WizardAfterSubmitTest extends TestCase
{
    use CreatesMarketplaceFixtures, DatabaseTransactions;

    private const WARNING = 'This listing has already been submitted for review. You can edit it from here.';

    /** @return array<string, array{string}> */
    public static function wizardPages(): array
    {
        return [
            'info' => ['properties.wizard.info.edit'],
            'location' => ['properties.wizard.location.edit'],
            'amenities' => ['properties.wizard.amenities'],
            'documents' => ['properties.wizard.documents'],
            'units' => ['properties.wizard.units'],
            'review' => ['properties.wizard.review'],
        ];
    }

    /** @return array<string, array{string, string}> a submitted listing is Pending review, Published or Unpublished */
    public static function submittedStates(): array
    {
        return [
            'pending, published' => ['Pending', 'Published'],
            'approved, published' => ['Approved', 'Published'],
            'pending, unpublished' => ['Pending', 'Unpublished'],
        ];
    }

    #[DataProvider('wizardPages')]
    public function test_a_submitted_listing_sends_the_landlord_to_the_property_page_not_a_409(string $route): void
    {
        $landlord = $this->makeLandlord();
        $property = $this->makeProperty($landlord, ['verification_status' => 'Pending', 'publication_status' => 'Published']);

        $this->actingAs($landlord)
            ->get(route($route, $property))
            ->assertRedirect(route('landlord.properties.show', $property))
            ->assertSessionHas('warning', self::WARNING);
    }

    #[DataProvider('submittedStates')]
    public function test_every_submitted_state_is_redirected(string $verification, string $publication): void
    {
        $landlord = $this->makeLandlord();
        $property = $this->makeProperty($landlord, ['verification_status' => $verification, 'publication_status' => $publication]);

        $this->actingAs($landlord)
            ->get(route('properties.wizard.review', $property))
            ->assertRedirect(route('landlord.properties.show', $property));
    }

    public function test_submitting_again_after_the_listing_was_submitted_is_redirected_too(): void
    {
        $landlord = $this->makeLandlord();
        $property = $this->makeProperty($landlord, ['verification_status' => 'Pending', 'publication_status' => 'Published']);

        $this->actingAs($landlord)
            ->post(route('properties.wizard.submit', $property))
            ->assertRedirect(route('landlord.properties.show', $property))
            ->assertSessionHas('warning', self::WARNING);

        $this->assertSame('Published', $property->fresh()->publication_status);
    }

    public function test_the_redirect_lands_on_a_page_that_loads(): void
    {
        $landlord = $this->makeLandlord();
        $property = $this->makeProperty($landlord, ['verification_status' => 'Pending', 'publication_status' => 'Published']);

        $this->actingAs($landlord)
            ->followingRedirects()
            ->get(route('properties.wizard.review', $property))
            ->assertOk();
    }

    public function test_a_real_draft_still_opens_its_wizard_pages(): void
    {
        $landlord = $this->makeLandlord();
        $draft = $this->makeProperty($landlord, ['verification_status' => 'Pending', 'publication_status' => 'Draft']);

        $this->actingAs($landlord)->get(route('properties.wizard.info.edit', $draft))->assertOk();
        $this->actingAs($landlord)->get(route('properties.wizard.location.edit', $draft))->assertOk();
        $this->actingAs($landlord)->get(route('properties.wizard.amenities', $draft))->assertOk();
    }

    public function test_another_landlord_still_gets_a_403_not_a_redirect_to_someone_elses_listing(): void
    {
        $owner = $this->makeLandlord();
        $property = $this->makeProperty($owner, ['verification_status' => 'Pending', 'publication_status' => 'Published']);

        $this->actingAs($this->makeLandlord())
            ->get(route('properties.wizard.review', $property))
            ->assertForbidden();

        $this->actingAs($this->makeLandlord())
            ->post(route('properties.wizard.submit', $property))
            ->assertForbidden();
    }
}
