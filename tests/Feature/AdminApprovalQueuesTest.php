<?php

namespace Tests\Feature;

use App\Models\LandlordVerification;
use App\Models\Property;
use App\Models\PropertyDocument;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\TestCase;

/**
 * The four approval queues (plans/admin-approval-queues-redesign.md): units and documents as a lean table, listings and
 * landlord applications as a review desk that opens the item in ?open=. Decisions keep their routes; these tests cover
 * what the new pages show and that the desk selects the right row.
 */
class AdminApprovalQueuesTest extends TestCase
{
    use CreatesMarketplaceFixtures, DatabaseTransactions;

    private function admin()
    {
        return $this->makeUserWithRole('Admin');
    }

    private function pendingListing(string $title): Property
    {
        $property = $this->makeProperty($this->makeLandlord(), ['title' => $title, 'verification_status' => 'Pending']);
        $this->makeUnit($property, ['unit_label' => 'Room A', 'verification_status' => 'Pending']);

        return $property;
    }

    private function application(array $attributes = []): LandlordVerification
    {
        return LandlordVerification::create(array_merge([
            'user_id' => $this->makeTenant()->user_id, 'government_id' => 'x', 'id_image_hash' => bin2hex(random_bytes(32)),
            'id_type' => 'Driver License', 'selfie' => 'x', 'business_name' => 'Desk Test Rooms', 'contact_number' => '0917',
            'business_address' => 'Somewhere', 'verification_status' => 'Pending', 'submitted_at' => now(),
            'face_match_status' => 'match', 'face_match_score' => 91.25, 'liveness_passed' => true,
        ], $attributes));
    }

    // ─── Units (the approval queue lives here now) ─────────────

    public function test_the_units_page_decides_from_the_row_and_asks_for_a_reason_to_reject(): void
    {
        $property = $this->makeProperty($this->makeLandlord(), ['verification_status' => 'Approved']);
        $unit = $this->makeUnit($property, ['unit_label' => 'Bed Q', 'verification_status' => 'Pending']);

        $this->actingAs($this->admin())->get(route('admin.catalogue.units.index', ['needs' => 'approval']))
            ->assertOk()
            ->assertSee('Bed Q')
            ->assertSee('Show all units')
            ->assertSee(route('admin.units.approve', [$property, $unit]), false)
            ->assertSee('reject-unit', false);
    }

    public function test_the_units_page_points_at_waiting_units_and_filters_to_them(): void
    {
        $approved = $this->makeProperty($this->makeLandlord(), ['verification_status' => 'Approved']);
        $this->makeUnit($approved, ['unit_label' => 'Waiting Bed', 'verification_status' => 'Pending']);
        $this->makeUnit($approved, ['unit_label' => 'Settled Bed', 'verification_status' => 'Approved']);
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.catalogue.units.index'))
            ->assertOk()->assertSee('need')->assertSee('Show them')->assertSee('Settled Bed');

        $this->actingAs($admin)->get(route('admin.catalogue.units.index', ['needs' => 'approval']))
            ->assertOk()->assertSee('Waiting Bed')->assertDontSee('Settled Bed');
    }

    public function test_the_old_unit_approvals_address_still_lands_on_the_right_view(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.units.index', ['status' => 'Pending']))
            ->assertRedirect(route('admin.catalogue.units.index', ['needs' => 'approval']));
        $this->actingAs($admin)->get(route('admin.units.index', ['status' => 'Rejected']))
            ->assertRedirect(route('admin.catalogue.units.index', ['verification_status' => 'Rejected']));
        $this->actingAs($admin)->get(route('admin.units.index'))
            ->assertRedirect(route('admin.catalogue.units.index'));
    }

    public function test_deciding_a_unit_returns_to_the_units_page(): void
    {
        $property = $this->makeProperty($this->makeLandlord(), ['verification_status' => 'Approved']);
        $unit = $this->makeUnit($property, ['unit_label' => 'Decide Me', 'verification_status' => 'Pending']);

        $this->actingAs($this->admin())->post(route('admin.units.approve', [$property, $unit]))
            ->assertRedirect(route('admin.catalogue.units.index', ['needs' => 'approval']));
        $this->assertSame('Approved', $unit->fresh()->verification_status);
    }

    // ─── Property Documents ────────────────────────────────────

    public function test_the_document_queue_lists_a_waiting_document_with_a_review_link(): void
    {
        $property = $this->makeProperty($this->makeLandlord());
        $document = PropertyDocument::create([
            'property_id' => $property->property_id, 'document_type' => 'Business permit', 'document_number' => 'BP-77',
            'file_path' => 'property-documents/x.pdf', 'file_name' => 'x.pdf', 'status' => 'Pending',
        ]);

        $this->actingAs($this->admin())->get(route('admin.documents.index'))
            ->assertOk()
            ->assertSee('Business permit')->assertSee('BP-77')
            ->assertSee(route('admin.documents.show', $document), false)
            ->assertSee('Before you verify');
    }

    public function test_the_empty_document_queue_explains_itself(): void
    {
        $this->actingAs($this->admin())->get(route('admin.documents.index', ['status' => 'Verified']))
            ->assertOk()->assertSee('No verified documents yet')->assertSee('See all documents');
    }

    // ─── Property Verifications (desk) ─────────────────────────

    public function test_the_listing_desk_opens_the_first_listing_and_then_the_one_in_open(): void
    {
        $first = $this->pendingListing('Aardvark Lodge');
        $this->travel(1)->minutes();
        $second = $this->pendingListing('Zebra Heights');
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.listings.approval'))
            ->assertOk()
            ->assertSee(route('admin.listings.approve', $first->property_id), false)
            ->assertDontSee(route('admin.listings.approve', $second->property_id), false)
            ->assertSee('Zebra Heights');

        $this->actingAs($admin)->get(route('admin.listings.approval', ['open' => $second->property_id]))
            ->assertOk()
            ->assertSee(route('admin.listings.approve', $second->property_id), false)
            ->assertDontSee(route('admin.listings.approve', $first->property_id), false);
    }

    public function test_the_listing_desk_shows_the_checks_the_map_and_the_units_to_decide_on(): void
    {
        $property = $this->pendingListing('Checks Lodge');
        $property->update(['description' => 'A quiet place near the plaza.', 'house_rules' => ['No Pets'], 'latitude' => 10.30, 'longitude' => 123.90]);

        $this->actingAs($this->admin())->get(route('admin.listings.approval'))
            ->assertOk()
            ->assertSee('checks pass')
            ->assertSee('Map pin')
            ->assertSee('admin-property-map', false)
            ->assertSee("Room A")
            ->assertSee('A quiet place near the plaza.')
            ->assertSee('No Pets')
            ->assertSee('Application not approved');
    }

    public function test_the_listing_desk_has_no_decision_for_a_decided_listing_and_unsuspend_for_a_suspended_one(): void
    {
        $approved = $this->makeProperty($this->makeLandlord(), ['title' => 'Decided House', 'verification_status' => 'Approved']);
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.listings.approval', ['status' => 'Approved']))
            ->assertOk()->assertSee('Decided House')
            ->assertDontSee(route('admin.listings.approve', $approved->property_id), false);

        $approved->update(['publication_status' => 'Suspended']);
        $this->actingAs($admin)->get(route('admin.listings.approval', ['status' => 'Suspended']))
            ->assertOk()->assertSee(route('admin.listings.unsuspend', $approved->property_id), false);
    }

    public function test_the_empty_listing_queue_explains_itself(): void
    {
        $this->actingAs($this->admin())->get(route('admin.listings.approval'))
            ->assertOk()->assertSee('No listings yet');
        $this->actingAs($this->admin())->get(route('admin.listings.approval', ['status' => 'Pending']))
            ->assertOk()->assertSee('No listings waiting');
    }

    // ─── Landlord Verifications (desk) ─────────────────────────

    public function test_the_landlord_desk_shows_id_and_selfie_side_by_side_with_the_checks(): void
    {
        $verification = $this->application(['face_match_status' => 'mismatch', 'face_match_score' => 12.5, 'liveness_passed' => false]);

        $this->actingAs($this->admin())->get(route('admin.verifications.index'))
            ->assertOk()
            ->assertSee(route('verifications.preview', [$verification, 'front']), false)
            ->assertSee(route('verifications.preview', [$verification, 'selfie']), false)
            ->assertSee('Needs a closer look')
            ->assertSee('Does not match (12.5%)')
            ->assertSee('Not done.')
            ->assertSee(route('admin.verifications.approve', $verification), false)
            ->assertSee(route('admin.verifications.reject', $verification), false);
    }

    public function test_the_landlord_desk_opens_the_chosen_application(): void
    {
        $first = $this->application(['business_name' => 'First Rooms', 'submitted_at' => now()->subDays(3)]);
        $second = $this->application(['business_name' => 'Second Rooms', 'submitted_at' => now()->subDay()]);
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.verifications.index'))
            ->assertOk()->assertSee(route('admin.verifications.approve', $first), false)
            ->assertDontSee(route('admin.verifications.approve', $second), false);

        $this->actingAs($admin)->get(route('admin.verifications.index', ['open' => $second->verification_id]))
            ->assertOk()->assertSee(route('admin.verifications.approve', $second), false)
            ->assertDontSee(route('admin.verifications.approve', $first), false);
    }

    public function test_a_decided_application_shows_its_reason_and_no_buttons(): void
    {
        $verification = $this->application(['verification_status' => 'Rejected', 'admin_notes' => 'The ID is cropped', 'reviewed_at' => now()]);

        $this->actingAs($this->admin())->get(route('admin.verifications.index', ['status' => 'Rejected']))
            ->assertOk()->assertSee('The ID is cropped')
            ->assertDontSee(route('admin.verifications.approve', $verification), false);
    }

    public function test_the_empty_landlord_queue_explains_itself(): void
    {
        $this->actingAs($this->admin())->get(route('admin.verifications.index'))
            ->assertOk()->assertSee('No applications yet');
        $this->actingAs($this->admin())->get(route('admin.verifications.index', ['status' => 'Pending']))
            ->assertOk()->assertSee('No applications waiting');
    }

    // ─── Open on All, waiting first ────────────────────────────

    public function test_every_queue_opens_on_all_with_waiting_items_first(): void
    {
        $admin = $this->admin();

        // Landlord applications: a newer waiting one still sits above an older decided one.
        $decided = $this->application(['verification_status' => 'Approved', 'reviewed_at' => now(), 'submitted_at' => now()->subDay()]);
        $waiting = $this->application(['verification_status' => 'Pending', 'submitted_at' => now()->subHour()]);
        $response = $this->actingAs($admin)->get(route('admin.verifications.index'))->assertOk();
        $this->assertSame('All', $response->viewData('status'));
        $this->assertSame([$waiting->verification_id, $decided->verification_id], $response->viewData('verifications')->pluck('verification_id')->take(2)->all());

        // Listings.
        $approved = $this->makeProperty($this->makeLandlord(), ['title' => 'Old Approved', 'verification_status' => 'Approved']);
        $this->travel(1)->minutes();
        $pending = $this->pendingListing('New Pending');
        $response = $this->actingAs($admin)->get(route('admin.listings.approval'))->assertOk();
        $this->assertSame('All', $response->viewData('status'));
        $this->assertSame([$pending->property_id, $approved->property_id], $response->viewData('pendingListings')->pluck('property_id')->take(2)->all());

        // Documents and units default to All too.
        $this->assertSame('All', $this->actingAs($admin)->get(route('admin.documents.index'))->viewData('status'));
    }

    public function test_the_units_page_offers_decisions_only_where_the_unit_needs_its_own(): void
    {
        $approvedProperty = $this->makeProperty($this->makeLandlord(), ['verification_status' => 'Approved']);
        $own = $this->makeUnit($approvedProperty, ['unit_label' => 'Own Decision', 'verification_status' => 'Pending']);
        $pendingProperty = $this->makeProperty($this->makeLandlord(), ['verification_status' => 'Pending']);
        $withProperty = $this->makeUnit($pendingProperty, ['unit_label' => 'With Property', 'verification_status' => 'Pending']);

        $this->actingAs($this->admin())->get(route('admin.catalogue.units.index'))
            ->assertOk()
            ->assertSee('Own Decision')->assertSee('With Property')
            ->assertSee(route('admin.units.approve', [$approvedProperty, $own]), false)
            ->assertDontSee(route('admin.units.approve', [$pendingProperty, $withProperty]), false);
    }
}
