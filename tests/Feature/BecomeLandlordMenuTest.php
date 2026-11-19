<?php

namespace Tests\Feature;

use App\Models\LandlordVerification;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\TestCase;

/**
 * The account menu gives a tenant a way into the landlord application: "Become a landlord" until they apply, then
 * "Verification status". Landlords and admins never see it.
 */
class BecomeLandlordMenuTest extends TestCase
{
    use CreatesMarketplaceFixtures, DatabaseTransactions;

    private function menuOf($user): string
    {
        $html = $this->actingAs($user)->get(route('properties.index'))->assertOk()->getContent();
        $this->assertStringContainsString('Account Settings', $html, 'the account menu is on this page, so its absences mean something');

        return $html;
    }

    private function applicationFor($user, string $status): void
    {
        LandlordVerification::create([
            'user_id' => $user->user_id, 'government_id' => 'x.jpg', 'id_type' => 'PhilSys', 'selfie' => 'y.jpg',
            'id_image_hash' => str_repeat('d', 64), 'verification_status' => $status,
        ]);
    }

    public function test_a_tenant_who_has_not_applied_sees_become_a_landlord(): void
    {
        $html = $this->menuOf($this->makeTenant());

        $this->assertStringContainsString('data-account-menu="become-landlord"', $html);
        $this->assertStringContainsString('Become a landlord', $html);
        $this->assertStringContainsString('href="'.route('landlord.verification.create').'"', $html);
    }

    public function test_a_tenant_with_a_pending_application_sees_verification_status_instead(): void
    {
        $tenant = $this->makeTenant();
        $this->applicationFor($tenant, 'Pending');

        $html = $this->menuOf($tenant);

        $this->assertStringNotContainsString('Become a landlord', $html);
        $this->assertStringContainsString('data-account-menu="landlord-status"', $html);
        $this->assertStringNotContainsString('href="'.route('landlord.verification.create').'"', $html);
    }

    public function test_a_rejected_applicant_is_pointed_at_their_status_where_they_can_apply_again(): void
    {
        $tenant = $this->makeTenant();
        $this->applicationFor($tenant, 'Rejected');

        $html = $this->menuOf($tenant);

        $this->assertStringNotContainsString('Become a landlord', $html);
        $this->assertStringContainsString('data-account-menu="landlord-status"', $html);
    }

    public function test_a_landlord_does_not_see_become_a_landlord(): void
    {
        $html = $this->menuOf($this->makeLandlord());

        $this->assertStringNotContainsString('Become a landlord', $html);
        $this->assertStringNotContainsString('data-account-menu=', $html);
    }

    public function test_an_admin_does_not_see_become_a_landlord(): void
    {
        $html = $this->menuOf($this->makeUserWithRole('Admin'));

        $this->assertStringNotContainsString('Become a landlord', $html);
        $this->assertStringNotContainsString('data-account-menu=', $html);
    }
}
