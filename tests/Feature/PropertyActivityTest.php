<?php

namespace Tests\Feature;

use App\Models\PropertyActivity;
use App\Models\Reservation;
use App\Models\Review;
use App\Models\UnitMedia;
use Cloudinary\Api\Upload\UploadApi;
use Cloudinary\Cloudinary;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\Support\CreatesPaymentFixtures;
use Tests\TestCase;

/**
 * A property's Activity Log is written by observers, so every path that changes a listing (web form, wizard,
 * mobile API, an admin decision, a webhook) leaves the same trail, with the actor read as a role.
 */
class PropertyActivityTest extends TestCase
{
    use CreatesMarketplaceFixtures, CreatesPaymentFixtures, DatabaseTransactions;

    protected function tearDown(): void
    {
        PropertyActivity::flushEventListeners();

        parent::tearDown();
    }

    /** @return Collection<int, PropertyActivity> */
    private function lines(int $propertyId)
    {
        return PropertyActivity::where('property_id', $propertyId)->orderBy('activity_id')->get();
    }

    private function line(int $propertyId, string $action): ?PropertyActivity
    {
        return $this->lines($propertyId)->firstWhere('action', $action);
    }

    // ─── who acted ──────────────────────────────────────────────

    public function test_the_actor_is_read_as_a_role(): void
    {
        $landlord = $this->makeLandlord();
        $property = $this->makeProperty($landlord);
        $admin = $this->makeUserWithRole('Admin');
        $tenant = $this->makeTenant();

        $this->actingAs($landlord);
        PropertyActivity::record($property->property_id, 'test.landlord', 'By the owner');
        $this->actingAs($admin);
        PropertyActivity::record($property->property_id, 'test.admin', 'By an admin');
        $this->actingAs($tenant);
        PropertyActivity::record($property->property_id, 'test.tenant', 'By someone else');
        auth()->logout();
        PropertyActivity::record($property->property_id, 'test.system', 'By a job');

        $this->assertSame('landlord', $this->line($property->property_id, 'test.landlord')->actor_role);
        $this->assertSame('admin', $this->line($property->property_id, 'test.admin')->actor_role);
        $this->assertSame('tenant', $this->line($property->property_id, 'test.tenant')->actor_role);

        $system = $this->line($property->property_id, 'test.system');
        $this->assertSame('system', $system->actor_role);
        $this->assertNull($system->actor_id);
        $this->assertSame('System', $system->actor_name);
    }

    public function test_an_admin_who_owns_the_property_is_its_landlord_there(): void
    {
        $landlord = $this->makeLandlord();
        $landlord->assignRole('Admin');
        $property = $this->makeProperty($landlord);

        $this->actingAs($landlord);
        PropertyActivity::record($property->property_id, 'test.both', 'Own listing');

        $this->assertSame('landlord', $this->line($property->property_id, 'test.both')->actor_role);
    }

    // ─── the listing ────────────────────────────────────────────

    public function test_creating_a_property_and_a_unit_writes_lines_for_the_landlord(): void
    {
        $landlord = $this->makeLandlord();
        $this->actingAs($landlord);

        $property = $this->makeProperty($landlord, ['title' => 'Sunrise House']);
        $this->makeUnit($property, ['unit_label' => 'Bed A']);

        $created = $this->line($property->property_id, 'property.created');
        $this->assertStringContainsString('Sunrise House', $created->summary);
        $this->assertSame('landlord', $created->actor_role);
        $this->assertSame($landlord->user_id, $created->actor_id);

        $unit = $this->line($property->property_id, 'unit.added');
        $this->assertSame('Added unit "Bed A"', $unit->summary);
        $this->assertNotNull($unit->unit_id);
    }

    public function test_a_draft_is_quiet_until_it_is_submitted(): void
    {
        $landlord = $this->makeLandlord();
        $this->actingAs($landlord);
        $property = $this->makeProperty($landlord, ['publication_status' => 'Draft', 'verification_status' => 'Pending']);

        $unit = $this->makeUnit($property);
        $unit->update(['rental_fee' => 6000]);
        $property->update(['title' => 'Renamed while drafting']);

        $this->assertSame(['property.created'], $this->lines($property->property_id)->pluck('action')->all());

        $property->update(['publication_status' => 'Published']);

        $this->assertSame(
            ['property.created', 'property.submitted'],
            $this->lines($property->property_id)->pluck('action')->all()
        );
    }

    public function test_editing_a_property_names_what_changed_and_that_it_went_back_for_review(): void
    {
        $landlord = $this->makeLandlord();
        $property = $this->makeProperty($landlord);
        $this->actingAs($landlord);

        $property->update(['title' => 'New title', 'address' => 'New street', 'verification_status' => 'Pending']);

        $line = $this->line($property->property_id, 'property.updated');
        $this->assertSame('Edited the listing (title, address). It went back for admin review.', $line->summary);
        $this->assertSame(['title', 'address'], $line->metadata['fields']);
        $this->assertTrue($line->metadata['requeued']);
        $this->assertSame(1, $this->lines($property->property_id)->where('action', 'property.updated')->count());
    }

    public function test_an_edit_that_only_sends_the_listing_back_for_review_says_so(): void
    {
        $landlord = $this->makeLandlord();
        $property = $this->makeProperty($landlord);
        $this->actingAs($landlord);

        // Adding a photo requeues the listing without touching any field.
        $property->update(['verification_status' => 'Pending']);

        $this->assertNotNull($this->line($property->property_id, 'property.requeued'));
        $this->assertNull($this->line($property->property_id, 'property.updated'));
    }

    public function test_private_changes_and_noise_are_not_a_line(): void
    {
        $landlord = $this->makeLandlord();
        $property = $this->makeProperty($landlord);
        $this->actingAs($landlord);

        $property->update(['rejection_reason' => null]);
        $property->touch();

        $this->assertSame(['property.created'], $this->lines($property->property_id)->pluck('action')->all());
    }

    public function test_an_admin_decision_is_an_admin_line(): void
    {
        $property = $this->makeProperty($this->makeLandlord(), ['verification_status' => 'Pending']);
        $unit = $this->makeUnit($property, ['verification_status' => 'Pending']);
        $admin = $this->makeUserWithRole('Admin');
        $this->actingAs($admin);

        $property->update(['verification_status' => 'Approved']);
        $unit->update(['verification_status' => 'Rejected', 'rejection_reason' => 'Photos too dark']);

        $approved = $this->line($property->property_id, 'property.approved');
        $this->assertSame('admin', $approved->actor_role);
        $this->assertSame('Listing approved', $approved->summary);

        $rejected = $this->line($property->property_id, 'unit.rejected');
        $this->assertSame('admin', $rejected->actor_role);
        $this->assertStringContainsString('Photos too dark', $rejected->summary);
    }

    public function test_publishing_hiding_and_suspending_are_lines(): void
    {
        $landlord = $this->makeLandlord();
        $property = $this->makeProperty($landlord);
        $this->actingAs($landlord);

        $property->update(['publication_status' => 'Unpublished']);
        $property->update(['publication_status' => 'Published']);
        $this->actingAs($this->makeUserWithRole('Admin'));
        $property->update(['publication_status' => 'Suspended']);
        $property->update(['publication_status' => 'Published']);

        $this->assertSame(
            ['property.unpublished', 'property.published', 'property.suspended', 'property.reinstated'],
            $this->lines($property->property_id)->pluck('action')->slice(1)->values()->all()
        );
        $this->assertSame('admin', $this->line($property->property_id, 'property.suspended')->actor_role);
    }

    // ─── units ──────────────────────────────────────────────────

    public function test_editing_a_unit_over_the_web_writes_one_line_with_the_changed_fields(): void
    {
        $landlord = $this->makeLandlord();
        $property = $this->makeProperty($landlord);
        $unit = $this->makeUnit($property, ['unit_label' => 'Bed A']);

        $this->actingAs($landlord)->put(route('landlord.properties.units.update', [$property, $unit]), [
            'unit_label' => 'Bed A', 'rental_fee' => 6500, 'occupancy_limit' => 2, 'availability_status' => 'Available',
        ])->assertRedirect();

        $line = $this->lines($property->property_id)->firstWhere('action', 'unit.updated');
        $this->assertSame('landlord', $line->actor_role);
        $this->assertSame($unit->unit_id, $line->unit_id);
        $this->assertStringContainsString('rent', $line->summary);
        $this->assertStringContainsString('went back for admin review', $line->summary);
    }

    public function test_editing_a_unit_over_the_mobile_api_leaves_the_same_line(): void
    {
        $landlord = $this->makeLandlord();
        $property = $this->makeProperty($landlord);
        $unit = $this->makeUnit($property, ['unit_label' => 'Bed A']);

        $this->actingAs($landlord, 'sanctum')
            ->putJson('/api/v1/landlord/properties/'.$property->property_id.'/units/'.$unit->unit_id, [
                'unit_label' => 'Bed A', 'rental_fee' => 7000, 'security_deposit' => 5000,
                'occupancy_limit' => 2, 'availability_status' => 'Available',
            ])->assertOk();

        $line = $this->lines($property->property_id)->firstWhere('action', 'unit.updated');
        $this->assertNotNull($line);
        $this->assertSame('landlord', $line->actor_role);
    }

    public function test_a_status_only_change_is_not_a_unit_edit(): void
    {
        $landlord = $this->makeLandlord();
        $property = $this->makeProperty($landlord);
        $unit = $this->makeUnit($property);
        $this->actingAs($landlord);

        $unit->update(['availability_status' => 'Maintenance']);

        $this->assertNull($this->line($property->property_id, 'unit.updated'));
    }

    public function test_removing_a_unit_keeps_its_line_and_does_not_log_its_photos_going(): void
    {
        $api = Mockery::mock(UploadApi::class);
        $api->shouldReceive('destroy')->andReturn([]);
        $client = Mockery::mock(Cloudinary::class);
        $client->shouldReceive('uploadApi')->andReturn($api);
        $this->app->instance(Cloudinary::class, $client);

        $landlord = $this->makeLandlord();
        $property = $this->makeProperty($landlord);
        $unit = $this->makeUnit($property, ['unit_label' => 'Bed A']);
        foreach (range(1, 3) as $n) {
            $unit->media()->create(['media_type' => 'Image', 'media_url' => "https://example.test/u{$n}.jpg", 'source' => 'upload']);
        }

        $this->actingAs($landlord)
            ->delete(route('landlord.properties.units.destroy', [$property, $unit]))
            ->assertRedirect();

        $this->assertDatabaseMissing('property_units', ['unit_id' => $unit->unit_id]);

        $removed = $this->line($property->property_id, 'unit.removed');
        $this->assertSame('Removed unit "Bed A"', $removed->summary);
        $this->assertSame($unit->unit_id, $removed->unit_id);
        $this->assertNull($this->line($property->property_id, 'unit.photo_removed'));
        // The photos the test added went to the log too ("Added 3 photos to unit"), the removal did not.
        $this->assertSame(1, $this->lines($property->property_id)->where('action', 'unit.photo_added')->count());
    }

    // ─── photos ─────────────────────────────────────────────────

    public function test_photos_added_together_are_one_line_and_a_different_person_is_another(): void
    {
        $landlord = $this->makeLandlord();
        $property = $this->makeProperty($landlord);
        $this->actingAs($landlord);

        foreach (range(1, 3) as $n) {
            $property->media()->create(['media_type' => 'Image', 'media_url' => "https://example.test/p{$n}.jpg"]);
        }

        $lines = $this->lines($property->property_id)->where('action', 'photo.added');
        $this->assertCount(1, $lines);
        $this->assertSame('Added 3 photos', $lines->first()->summary);
        $this->assertSame(3, $lines->first()->metadata['count']);

        $this->actingAs($this->makeUserWithRole('Admin'));
        $property->media()->create(['media_type' => 'Image', 'media_url' => 'https://example.test/p4.jpg']);

        $this->assertCount(2, $this->lines($property->property_id)->where('action', 'photo.added'));
    }

    public function test_a_single_video_reads_as_a_video(): void
    {
        $landlord = $this->makeLandlord();
        $property = $this->makeProperty($landlord);
        $this->actingAs($landlord);

        $property->media()->create(['media_type' => 'Video', 'media_url' => 'https://example.test/v.mp4']);

        $this->assertSame('Added a video', $this->line($property->property_id, 'video.added')->summary);
    }

    // ─── documents ──────────────────────────────────────────────

    public function test_a_document_is_requested_uploaded_then_verified_by_the_right_people(): void
    {
        $landlord = $this->makeLandlord();
        $property = $this->makeProperty($landlord);
        $admin = $this->makeUserWithRole('Admin');

        $this->actingAs($admin);
        $document = $property->documents()->create([
            'document_type' => 'Proof of Ownership', 'file_path' => null, 'file_name' => null, 'status' => 'Pending', 'requested_by' => $admin->user_id,
        ]);

        $this->actingAs($landlord);
        $document->update(['file_path' => 'property_documents/1/x.pdf', 'file_name' => 'x.pdf']);

        $this->actingAs($admin);
        $document->update(['status' => 'Verified']);

        $this->assertSame('admin', $this->line($property->property_id, 'document.requested')->actor_role);
        $this->assertSame('landlord', $this->line($property->property_id, 'document.uploaded')->actor_role);
        $verified = $this->line($property->property_id, 'document.verified');
        $this->assertSame('admin', $verified->actor_role);
        $this->assertSame('Proof of Ownership verified', $verified->summary);
        // Never the file itself.
        $this->assertStringNotContainsString('x.pdf', implode(' ', $this->lines($property->property_id)->pluck('summary')->all()));
    }

    // ─── tenancies, money and reviews ───────────────────────────

    public function test_a_tenancy_leaves_neutral_lines_with_the_role_of_whoever_acted(): void
    {
        config(['broadcasting.default' => 'null']);
        $landlord = $this->makeLandlord();
        $tenant = $this->makeTenant();
        $unit = $this->makeUnit($this->makeProperty($landlord), ['unit_label' => 'Bed A']);

        $this->actingAs($landlord);
        $reservation = Reservation::create([
            'tenant_id' => $tenant->user_id, 'property_id' => $unit->property_id, 'unit_id' => $unit->unit_id,
            'rental_status' => 'Inquiry', 'reservation_date' => '2026-11-01', 'agreed_monthly_rent' => 5000,
        ]);

        $reservation->update(['rental_status' => 'Pending Rental Agreement']);
        $this->actingAs($tenant);
        $reservation->update(['rental_status' => 'Rental Agreement Signed']);
        $this->actingAs($landlord);
        $reservation->update(['rental_status' => 'Occupied']);
        $reservation->update(['rental_status' => 'Completed']);

        $lines = $this->lines($unit->property_id)->filter(fn ($l) => str_starts_with($l->action, 'reservation.'))->values();

        $this->assertSame(
            ['reservation.inquiry', 'reservation.agreement_sent', 'reservation.signed', 'reservation.moved_in', 'reservation.moved_out'],
            $lines->pluck('action')->all()
        );
        // An inquiry is the tenant's even when another account's request carried it.
        $this->assertSame(['tenant', 'landlord', 'tenant', 'landlord', 'landlord'], $lines->pluck('actor_role')->all());
        $this->assertStringContainsString($tenant->first_name, $lines[0]->summary);
        $this->assertStringContainsString('"Bed A"', $lines[3]->summary);
        $this->assertSame($reservation->reservation_id, $lines[3]->metadata['reservation_id']);
    }

    public function test_a_job_that_cancels_a_reservation_is_the_system(): void
    {
        ['reservation' => $reservation] = $this->makeTenancy(['rental_status' => 'Inquiry']);
        auth()->logout();

        $reservation->update(['rental_status' => 'Cancelled']);

        $line = $this->line($reservation->property_id, 'reservation.cancelled');
        $this->assertSame('system', $line->actor_role);
    }

    public function test_one_recording_spread_over_months_is_one_line(): void
    {
        ['landlord' => $landlord, 'reservation' => $reservation] = $this->makeTenancy();
        $this->actingAs($landlord);

        foreach (['2026-08-01', '2026-09-01', '2026-10-01'] as $month) {
            $this->makeRecordedPayment($reservation, $landlord, ['billing_period' => $month, 'amount' => 5000]);
        }

        $lines = $this->lines($reservation->property_id)->where('action', 'payment.recorded');
        $this->assertCount(1, $lines);
        $this->assertStringContainsString('Recorded ₱15,000 rent from', $lines->first()->summary);
        $this->assertStringContainsString('(3 months)', $lines->first()->summary);
        $this->assertSame('landlord', $lines->first()->actor_role);
        $this->assertSame($reservation->reservation_id, $lines->first()->metadata['reservation_id']);
    }

    public function test_pending_money_is_not_a_line_and_a_settled_checkout_is_the_tenants(): void
    {
        ['reservation' => $reservation] = $this->makeTenancy();
        auth()->logout();

        $initial = $this->makePendingCheckout($reservation, 'cs_1', ['amount' => 5000]);
        $deposit = $this->makePendingCheckout($reservation, 'cs_1', ['payment_type' => 'Deposit', 'amount' => 3000]);
        $this->assertCount(0, $this->lines($reservation->property_id)->where('action', 'payment.paid'));

        // The webhook settles both rows with nobody signed in.
        foreach ([$initial, $deposit] as $row) {
            $row->update(['status' => 'Held', 'paid_at' => now()]);
        }

        $lines = $this->lines($reservation->property_id)->where('action', 'payment.paid');
        $this->assertCount(1, $lines);
        $this->assertSame('tenant', $lines->first()->actor_role);
        $this->assertStringContainsString('₱8,000', $lines->first()->summary);
        $this->assertStringContainsString('held by AbangananHub', $lines->first()->summary);
    }

    public function test_voiding_a_payment_is_a_line_by_whoever_voided_it(): void
    {
        ['landlord' => $landlord, 'reservation' => $reservation] = $this->makeTenancy();
        $payment = $this->makeRecordedPayment($reservation, $landlord, ['amount' => 5000]);
        $this->actingAs($landlord);

        $payment->update(['status' => 'Voided', 'voided_at' => now(), 'voided_by' => $landlord->user_id]);

        $line = $this->line($reservation->property_id, 'payment.voided');
        $this->assertSame('landlord', $line->actor_role);
        $this->assertStringContainsString('Voided a ₱5,000 payment from', $line->summary);
    }

    public function test_a_review_is_the_tenants_line_with_a_name_and_no_contact_details(): void
    {
        $landlord = $this->makeLandlord();
        $property = $this->makeProperty($landlord);
        $tenant = $this->makeTenant();
        $this->actingAs($landlord);

        Review::create([
            'tenant_id' => $tenant->user_id, 'property_id' => $property->property_id, 'landlord_id' => $landlord->user_id,
            'rating' => 4, 'review_comment' => 'Clean and quiet.',
        ]);

        $line = $this->line($property->property_id, 'review.posted');
        $this->assertSame('tenant', $line->actor_role);
        $this->assertSame($tenant->user_id, $line->actor_id);
        $this->assertStringContainsString('left a 4-star review', $line->summary);
        $this->assertStringNotContainsString((string) $tenant->email, $line->summary.json_encode($line->metadata));
        $this->assertStringNotContainsString('Clean and quiet', $line->summary.json_encode($line->metadata));
    }

    // ─── safety ─────────────────────────────────────────────────

    public function test_a_failing_history_write_never_breaks_the_action(): void
    {
        Log::spy();
        PropertyActivity::creating(fn () => throw new \RuntimeException('history is down'));

        $landlord = $this->makeLandlord();
        $property = $this->makeProperty($landlord);
        $this->actingAs($landlord);

        $property->update(['title' => 'Still saved']);

        $this->assertSame('Still saved', $property->fresh()->title);
        $this->assertCount(0, $this->lines($property->property_id));
        Log::shouldHaveReceived('warning')->atLeast()->once();
    }

    public function test_a_rolled_back_action_leaves_no_line(): void
    {
        $landlord = $this->makeLandlord();
        $property = $this->makeProperty($landlord);
        $this->actingAs($landlord);
        $before = $this->lines($property->property_id)->count();

        try {
            DB::transaction(function () use ($property) {
                $this->makeUnit($property, ['unit_label' => 'Phantom']);
                throw new \RuntimeException('abort');
            });
        } catch (\RuntimeException) {
            // expected
        }

        $this->assertSame($before, $this->lines($property->property_id)->count());
    }

    public function test_the_history_goes_with_the_property(): void
    {
        $landlord = $this->makeLandlord();
        $property = $this->makeProperty($landlord);
        $this->actingAs($landlord);
        $this->assertGreaterThan(0, $this->lines($property->property_id)->count());

        $property->delete();

        $this->assertSame(0, PropertyActivity::where('property_id', $property->property_id)->count());
    }

    public function test_the_table_is_written_once_apart_from_a_roll_up(): void
    {
        $this->assertNull((new PropertyActivity)->getUpdatedAtColumn());
        $this->assertFalse(\Schema::hasColumn('property_activities', 'updated_at'));
    }

    public function test_unit_media_rows_use_the_unit_media_model(): void
    {
        // Guards the observer wiring: UnitMedia is observed through #[ObservedBy], not the service provider.
        $landlord = $this->makeLandlord();
        $property = $this->makeProperty($landlord);
        $unit = $this->makeUnit($property, ['unit_label' => 'Bed A']);
        $this->actingAs($landlord);

        UnitMedia::create(['unit_id' => $unit->unit_id, 'media_type' => 'Image', 'media_url' => 'https://example.test/a.jpg', 'source' => 'upload']);

        $line = $this->line($property->property_id, 'unit.photo_added');
        $this->assertSame('Added a photo to unit "Bed A"', $line->summary);
        $this->assertSame($unit->unit_id, $line->unit_id);
    }
}
