<?php

namespace Tests\Feature;

use App\Models\Amenity;
use App\Models\AuditLog;
use App\Models\Notification;
use App\Models\Property;
use App\Models\PropertyActivity;
use App\Models\PropertyUnit;
use App\Models\UnitMedia;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\TestCase;

/**
 * Approving a property also approves its Pending units, so one submission is one decision (plans/approve-property-with-units.md).
 * Units decided on their own (added or edited after approval) keep their own queue.
 */
class ApprovePropertyWithUnitsTest extends TestCase
{
    use CreatesMarketplaceFixtures, DatabaseTransactions;

    /** A submitted listing: Pending, Published, with the given Pending units. */
    private function pendingListing(int $units = 3): Property
    {
        $property = $this->makeProperty($this->makeLandlord(), ['verification_status' => 'Pending']);
        for ($i = 1; $i <= $units; $i++) {
            $this->makeUnit($property, ['unit_label' => "Room {$i}", 'verification_status' => 'Pending']);
        }

        return $property->fresh();
    }

    private function approve(Property $property)
    {
        return $this->actingAs($this->makeUserWithRole('Admin'))->post(route('admin.listings.approve', $property->property_id));
    }

    public function test_approving_a_property_approves_its_pending_units(): void
    {
        $property = $this->pendingListing(3);

        $this->approve($property)->assertRedirect();

        $this->assertSame('Approved', $property->fresh()->verification_status);
        $this->assertSame(0, $property->units()->where('verification_status', '!=', 'Approved')->count());
    }

    public function test_a_rejected_unit_stays_rejected(): void
    {
        $property = $this->pendingListing(2);
        $held = $property->units()->first();
        $held->update(['verification_status' => 'Rejected', 'rejection_reason' => 'Photos do not match']);

        $this->approve($property)->assertRedirect();

        $this->assertSame('Rejected', $held->fresh()->verification_status);
        $this->assertSame('Approved', $property->fresh()->verification_status);
        $this->assertSame(1, $property->units()->where('verification_status', 'Approved')->count());
    }

    public function test_rejecting_a_property_leaves_its_units_pending(): void
    {
        $property = $this->pendingListing(2);

        $this->actingAs($this->makeUserWithRole('Admin'))
            ->post(route('admin.listings.reject', $property->property_id), ['rejection_reason' => 'The address does not exist.'])
            ->assertRedirect();

        $this->assertSame('Rejected', $property->fresh()->verification_status);
        $this->assertSame(2, $property->units()->where('verification_status', 'Pending')->count());
    }

    public function test_each_cascaded_unit_is_in_the_activity_log_and_the_audit_log(): void
    {
        $property = $this->pendingListing(2);
        PropertyActivity::where('property_id', $property->property_id)->delete();

        $this->approve($property);

        $this->assertSame(2, PropertyActivity::where('property_id', $property->property_id)->where('action', 'unit.approved')->count());
        $audit = AuditLog::where('action', 'unit.approve')->latest('log_id')->limit(2)->get();
        $this->assertCount(2, $audit);
        $this->assertSame('listing.approve', $audit->first()->metadata['via'] ?? null);
    }

    public function test_the_landlord_gets_one_notification_that_names_the_unit_count(): void
    {
        $property = $this->pendingListing(3);

        $this->approve($property);

        $rows = Notification::where('user_id', $property->landlord_id)->get();
        $this->assertCount(1, $rows);
        $this->assertStringContainsString('3 units', $rows->first()->message);
    }

    public function test_a_property_with_no_pending_units_keeps_the_plain_message(): void
    {
        $property = $this->pendingListing(0);

        $this->approve($property);

        $row = Notification::where('user_id', $property->landlord_id)->sole();
        $this->assertStringNotContainsString('unit', $row->message);
    }

    public function test_a_second_approve_is_refused_and_does_not_run_the_cascade_again(): void
    {
        $property = $this->pendingListing(2);
        $admin = $this->makeUserWithRole('Admin');

        $this->actingAs($admin)->post(route('admin.listings.approve', $property->property_id))->assertRedirect();
        $this->actingAs($admin)->post(route('admin.listings.approve', $property->property_id))->assertStatus(409);

        $this->assertSame(1, Notification::where('user_id', $property->landlord_id)->count());
    }

    public function test_a_pending_unit_on_a_pending_property_is_not_in_the_unit_queue_or_its_counts(): void
    {
        $property = $this->pendingListing(2);
        $admin = $this->makeUserWithRole('Admin');
        $before = PropertyUnit::awaitingOwnReview()->count();

        $this->actingAs($admin)->get(route('admin.units.index', ['status' => 'Pending']))->assertOk()
            ->assertDontSee('Room 1')->assertDontSee('Room 2');

        $this->assertSame(0, PropertyUnit::awaitingOwnReview()->where('property_id', $property->property_id)->count());
        $this->assertSame($before, PropertyUnit::awaitingOwnReview()->count());
    }

    public function test_a_pending_unit_added_later_to_an_approved_property_is_in_the_queue_and_the_dashboard_count(): void
    {
        $property = $this->makeProperty($this->makeLandlord());
        $this->makeUnit($property, ['unit_label' => 'Added later', 'verification_status' => 'Pending']);
        $admin = $this->makeUserWithRole('Admin');

        $this->assertSame(1, PropertyUnit::awaitingOwnReview()->where('property_id', $property->property_id)->count());
        $this->actingAs($admin)->get(route('admin.units.index', ['status' => 'Pending']))->assertOk()->assertSee('Added later');

        $dashboard = $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();
        $dashboard->assertSee('Unit approvals');
    }

    public function test_the_all_tab_count_is_the_real_unit_total(): void
    {
        $this->pendingListing(2);
        $admin = $this->makeUserWithRole('Admin');

        $response = $this->actingAs($admin)->get(route('admin.units.index', ['status' => 'All']))->assertOk();

        $this->assertSame(PropertyUnit::count(), $response->viewData('counts')['All']);
    }

    public function test_the_admin_property_page_says_how_many_units_approving_will_include(): void
    {
        $property = $this->pendingListing(3);

        $this->actingAs($this->makeUserWithRole('Admin'))->get(route('admin.catalogue.properties.show', $property))->assertOk()
            ->assertSee('also approves its 3 pending units');
    }

    public function test_submitting_a_listing_from_the_wizard_notifies_each_admin_once(): void
    {
        $landlord = $this->makeLandlord();
        $property = $this->makeProperty($landlord, ['verification_status' => 'Pending', 'publication_status' => 'Draft', 'title' => 'Wizard House']);
        $property->amenities()->attach(Amenity::create(['amenity_name' => 'Wizard test amenity', 'scope' => 'property', 'category' => 'General'])->amenity_id);
        foreach (['Proof of Ownership', 'Tax Declaration', 'Business Permit'] as $type) {
            $property->documents()->create(['document_type' => $type, 'file_path' => "private/{$type}.pdf", 'file_name' => "{$type}.pdf", 'status' => 'Pending']);
        }
        $unit = $this->makeUnit($property, ['verification_status' => 'Pending']);
        UnitMedia::create(['unit_id' => $unit->unit_id, 'media_type' => 'Image', 'media_url' => 'https://example.test/a.jpg', 'source' => 'upload']);
        $admin = $this->makeUserWithRole('Admin');

        $this->actingAs($landlord)->post(route('properties.wizard.submit', $property))->assertRedirect();

        $this->assertSame('Published', $property->fresh()->publication_status);
        $rows = Notification::where('user_id', $admin->user_id)->get();
        $this->assertCount(1, $rows);
        $this->assertStringContainsString('Wizard House', $rows->first()->message);
        $this->assertStringContainsString(route('admin.listings.approval'), $rows->first()->link);
    }

    public function test_the_landlord_sees_one_wait_not_two_while_the_property_is_pending(): void
    {
        $landlord = $this->makeLandlord();
        $property = $this->makeProperty($landlord, ['verification_status' => 'Pending']);
        $this->makeUnit($property, ['unit_label' => 'Room A', 'verification_status' => 'Pending']);

        $this->actingAs($landlord)->get(route('landlord.properties.show', $property))->assertOk()
            ->assertSee('Waiting with the listing');
        $this->actingAs($landlord)->get(route('landlord.units.index'))->assertOk()
            ->assertSee('Waiting with the listing');
    }

    public function test_a_unit_pending_on_an_approved_property_still_says_pending(): void
    {
        $landlord = $this->makeLandlord();
        $property = $this->makeProperty($landlord);
        $this->makeUnit($property, ['unit_label' => 'Room A', 'verification_status' => 'Pending']);

        $this->actingAs($landlord)->get(route('landlord.properties.show', $property))->assertOk()
            ->assertDontSee('Waiting with the listing')
            ->assertSee('Pending');
    }

    public function test_a_rejected_unit_keeps_its_own_label_on_a_pending_property(): void
    {
        $landlord = $this->makeLandlord();
        $property = $this->makeProperty($landlord, ['verification_status' => 'Pending']);
        $unit = $this->makeUnit($property, ['verification_status' => 'Rejected']);

        $this->assertSame('Rejected', $unit->verificationLabel('Pending'));
        $this->assertSame('Waiting with the listing', $unit->fill(['verification_status' => 'Pending'])->verificationLabel('Pending'));
        $this->assertSame('Pending', $unit->verificationLabel('Approved'));
    }
}
