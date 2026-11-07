<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Report;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\TestCase;

/**
 * A landlord files a report from a modal on My Complaints instead of the /reports page, and the sidebar no
 * longer carries "Report a Problem".
 */
class LandlordFileReportModalTest extends TestCase
{
    use CreatesMarketplaceFixtures, DatabaseTransactions;

    public function test_my_complaints_carries_the_modal_with_the_people_messaged_and_no_sidebar_item(): void
    {
        $landlord = $this->makeLandlord();
        $tenant = $this->makeTenant();
        $tenant->forceFill(['first_name' => 'Kirby', 'last_name' => 'Lane'])->save();
        $unit = $this->makeUnit($this->makeProperty($landlord));
        Conversation::create([
            'tenant_id' => $tenant->user_id, 'landlord_id' => $landlord->user_id,
            'property_id' => $unit->property_id, 'unit_id' => $unit->unit_id, 'type' => 'inquiry',
        ]);

        $html = $this->actingAs($landlord)->get(route('landlord.complaints.index'))->assertOk()->getContent();

        $this->assertStringContainsString("\$dispatch('open-file-report')", $html);
        $this->assertStringContainsString('name="return_to" value="complaints"', $html);
        $this->assertStringContainsString('Kirby Lane', $html);
        $this->assertStringNotContainsString('Report a Problem', $html);
    }

    public function test_a_report_filed_from_the_modal_lands_back_on_my_complaints(): void
    {
        $landlord = $this->makeLandlord();
        $tenant = $this->makeTenant();

        $this->actingAs($landlord)->post(route('reports.store'), [
            'return_to' => 'complaints', 'target_type' => 'user', 'reported_user_id' => $tenant->user_id,
            'property_id' => '', 'category' => 'Harassment', 'details' => 'Rude messages',
        ])->assertRedirect(route('landlord.complaints.index'))->assertSessionHas('success');

        $this->assertDatabaseHas('reports', [
            'reporter_id' => $landlord->user_id, 'reported_user_id' => $tenant->user_id,
            'property_id' => null, 'report_reason' => 'Harassment: Rude messages',
        ]);
    }

    public function test_a_missing_reason_goes_back_with_the_error_so_the_modal_reopens(): void
    {
        $landlord = $this->makeLandlord();

        $this->actingAs($landlord)->from(route('landlord.complaints.index'))->post(route('reports.store'), [
            'return_to' => 'complaints', 'target_type' => 'user', 'reported_user_id' => $this->makeTenant()->user_id,
        ])->assertRedirect(route('landlord.complaints.index'))->assertSessionHasErrors('category');

        $this->assertSame(0, Report::where('reporter_id', $landlord->user_id)->count());
    }

    public function test_the_report_page_still_works_on_its_own(): void
    {
        $tenant = $this->makeTenant();

        $this->actingAs($tenant)->get(route('reports.create'))->assertOk();
        $this->post(route('reports.store'), [
            'target_type' => 'property', 'property_id' => $this->makeProperty($this->makeLandlord())->property_id, 'category' => 'Fake Listing',
        ])->assertRedirect(route('reports.create'));
    }
}
