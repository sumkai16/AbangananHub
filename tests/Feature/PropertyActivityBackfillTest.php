<?php

namespace Tests\Feature;

use App\Models\PropertyActivity;
use App\Models\Review;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\TestCase;

/** The one-time backfill: what the database already knew becomes history, once, and only what it can know. */
class PropertyActivityBackfillTest extends TestCase
{
    use CreatesMarketplaceFixtures, DatabaseTransactions;

    private function migration(): object
    {
        return require database_path('migrations/2026_11_05_000300_backfill_property_activities.php');
    }

    private function setUpHistoryAsItWasBeforeTheLog(): array
    {
        $landlord = $this->makeLandlord();
        $tenant = $this->makeTenant();
        $property = $this->makeProperty($landlord, ['title' => 'Old Listing']);
        $unit = $this->makeUnit($property, ['unit_label' => 'Bed A']);
        Review::create(['tenant_id' => $tenant->user_id, 'property_id' => $property->property_id, 'landlord_id' => $landlord->user_id, 'rating' => 5]);
        Review::create(['tenant_id' => $this->makeTenant()->user_id, 'property_id' => $property->property_id, 'landlord_id' => $landlord->user_id, 'rating' => 1, 'is_hidden' => true]);

        DB::table('audit_logs')->insert([
            'actor_id' => null, 'actor_name' => 'Site Admin', 'actor_email' => 'admin@example.test', 'action' => 'listing.approve',
            'auditable_type' => 'App\\Models\\Property', 'auditable_id' => $property->property_id, 'summary' => 'Approved listing', 'created_at' => '2026-10-02 09:00:00',
        ]);
        DB::table('audit_logs')->insert([
            'actor_id' => null, 'actor_name' => 'Site Admin', 'actor_email' => 'admin@example.test', 'action' => 'unit.reject',
            'auditable_type' => 'App\\Models\\PropertyUnit', 'auditable_id' => $unit->unit_id, 'summary' => 'Rejected unit', 'reason' => 'Blurry photos', 'created_at' => '2026-10-03 09:00:00',
        ]);
        DB::table('occupancy_activities')->insert([
            'landlord_id' => $landlord->user_id, 'property_id' => $property->property_id, 'unit_id' => $unit->unit_id,
            'actor_id' => $landlord->user_id, 'tenant_id' => null, 'from_status' => 'Available', 'to_status' => 'Occupied', 'created_at' => '2026-10-04 09:00:00', 'updated_at' => '2026-10-04 09:00:00',
        ]);

        // Start from "the log did not exist yet".
        PropertyActivity::where('property_id', $property->property_id)->delete();

        return [$landlord, $tenant, $property, $unit];
    }

    public function test_it_rebuilds_the_history_from_what_exists(): void
    {
        [$landlord, , $property, $unit] = $this->setUpHistoryAsItWasBeforeTheLog();

        $this->migration()->up();

        $lines = PropertyActivity::where('property_id', $property->property_id)->get()->keyBy('action');

        $this->assertSame('landlord', $lines['property.created']->actor_role);
        $this->assertSame($landlord->user_id, $lines['property.created']->actor_id);
        $this->assertSame('Created the listing "Old Listing"', $lines['property.created']->summary);

        $this->assertSame('Added unit "Bed A"', $lines['unit.added']->summary);
        $this->assertSame($unit->unit_id, $lines['unit.added']->unit_id);

        $this->assertSame('admin', $lines['property.approved']->actor_role);
        $this->assertSame('Site Admin', $lines['property.approved']->actor_name);
        $this->assertSame('2026-10-02 09:00:00', $lines['property.approved']->created_at->format('Y-m-d H:i:s'));
        $this->assertSame('Unit "Bed A" rejected: Blurry photos', $lines['unit.rejected']->summary);

        $this->assertSame('landlord', $lines['unit.status_changed']->actor_role);
        $this->assertSame('Unit "Bed A" went from Available to Occupied', $lines['unit.status_changed']->summary);

        // One visible review became a line; the hidden one did not.
        $this->assertSame(1, PropertyActivity::where('property_id', $property->property_id)->where('action', 'review.posted')->count());

        foreach ($lines as $line) {
            $this->assertTrue($line->metadata['backfilled']);
        }
    }

    public function test_running_it_twice_adds_nothing(): void
    {
        [, , $property] = $this->setUpHistoryAsItWasBeforeTheLog();

        $this->migration()->up();
        $once = PropertyActivity::where('property_id', $property->property_id)->count();
        $this->assertGreaterThan(0, $once);

        $this->migration()->up();

        $this->assertSame($once, PropertyActivity::where('property_id', $property->property_id)->count());
    }

    public function test_it_leaves_drafts_units_and_lines_written_by_the_live_code_alone(): void
    {
        $landlord = $this->makeLandlord();
        $draft = $this->makeProperty($landlord, ['publication_status' => 'Draft', 'verification_status' => 'Pending']);
        $this->makeUnit($draft);
        PropertyActivity::where('property_id', $draft->property_id)->delete();
        $live = PropertyActivity::create([
            'property_id' => $draft->property_id, 'actor_role' => 'landlord', 'actor_name' => 'Me', 'action' => 'photo.added', 'summary' => 'Added a photo', 'created_at' => now(),
        ]);

        $this->migration()->up();

        $actions = PropertyActivity::where('property_id', $draft->property_id)->pluck('action')->all();
        $this->assertContains('property.created', $actions);          // creation is always history
        $this->assertNotContains('unit.added', $actions);             // a draft's units are not, yet
        $this->assertContains('photo.added', $actions);

        $this->migration()->down();

        // down() removes exactly what it wrote.
        $this->assertSame(['photo.added'], PropertyActivity::where('property_id', $draft->property_id)->pluck('action')->all());
        $this->assertNotNull($live->fresh());
    }

    public function test_it_is_safe_on_empty_tables(): void
    {
        DB::table('property_activities')->delete();
        DB::table('occupancy_activities')->delete();
        DB::table('reviews')->delete();
        DB::table('property_units')->delete();
        DB::table('properties')->delete();
        DB::table('audit_logs')->delete();

        $this->migration()->up();

        $this->assertSame(0, PropertyActivity::count());
    }
}
