<?php

namespace Tests\Feature;

use App\Models\LandlordVerification;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\TestCase;

/**
 * A pending applicant must be able to get back to what they submitted, not land on a blank wizard.
 */
class VerificationStatusAccessTest extends TestCase
{
    use CreatesMarketplaceFixtures, DatabaseTransactions;

    private function apply(string $status, array $extra = []): User
    {
        $tenant = $this->makeTenant();
        LandlordVerification::create($extra + [
            'user_id' => $tenant->user_id,
            'government_id' => 'x', 'id_image_hash' => str_repeat('a', 64), 'id_type' => 'Driver License', 'selfie' => 'x',
            'business_name' => 'Test', 'contact_number' => '0917', 'business_address' => 'Somewhere',
            'verification_status' => $status, 'submitted_at' => now(),
        ]);

        return $tenant;
    }

    public function test_pending_applicant_is_sent_to_the_status_page(): void
    {
        $tenant = $this->apply('Pending');

        $this->actingAs($tenant)->get(route('landlord.verification.create'))
            ->assertRedirect(route('landlord.verification.show'));
    }

    public function test_rejected_applicant_still_gets_the_wizard(): void
    {
        $tenant = $this->apply('Rejected');

        $this->actingAs($tenant)->get(route('landlord.verification.create'))->assertOk();
    }

    public function test_navbar_links_to_status_once_an_application_exists(): void
    {
        $tenant = $this->apply('Pending');

        $this->actingAs($tenant)->get(route('home'))
            ->assertSee('Verification status')
            ->assertDontSee('Become a Landlord');
    }

    public function test_status_page_shows_submitted_details_with_sensitive_values_masked(): void
    {
        $tenant = $this->apply('Pending', [
            'id_number' => 'N03-12-345678', 'ocr_name' => 'ROBERTO REYES',
            'business_name' => 'Reyes Apartments', 'contact_number' => '09181112222',
            'business_address' => '12 Rizal St, Abanganan',
        ]);

        $this->actingAs($tenant)->get(route('landlord.verification.show'))
            ->assertOk()
            ->assertSee('ROBERTO REYES')
            ->assertSee('Reyes Apartments')
            ->assertSee('12 Rizal St, Abanganan')
            ->assertSee('•••••••••5678', false)
            ->assertSee('••••••••222', false)
            ->assertDontSee('N03-12-345678')
            ->assertDontSee('09181112222');
    }

    public function test_status_page_renders_for_approved_and_rejected_applications(): void
    {
        $approved = $this->apply('Approved', ['reviewed_at' => now()]);
        $this->actingAs($approved)->get(route('landlord.verification.show'))
            ->assertOk()->assertSee('a verified landlord')->assertSee('Go to your listings');

        $rejected = $this->apply('Rejected', ['reviewed_at' => now(), 'admin_notes' => 'Blurry ID photo']);
        $this->actingAs($rejected)->get(route('landlord.verification.show'))
            ->assertOk()->assertSee('Application not approved')->assertSee('Blurry ID photo')->assertSee('Update and resubmit');
    }
}
