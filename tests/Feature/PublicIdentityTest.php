<?php

namespace Tests\Feature;

use App\Models\Property;
use App\Models\RentalBusiness;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\TestCase;

/**
 * Public pages show the rental business, not the person (plans/business-first-identity-and-name-search.md,
 * phase 1): the listing page, the business page, public reviews and the public API.
 */
class PublicIdentityTest extends TestCase
{
    use CreatesMarketplaceFixtures, DatabaseTransactions;

    private User $landlord;

    private Property $property;

    protected function setUp(): void
    {
        parent::setUp();

        config(['broadcasting.default' => 'null']);

        $this->landlord = User::factory()->create([
            'first_name'         => 'Maria',
            'last_name'          => 'Santos',
            'email'              => 'maria.private@example.test',
            'contact_number'     => '09179998888',
            'profile_visibility' => 'public',
        ]);
        $this->landlord->assignRole('Landlord');

        RentalBusiness::create([
            'landlord_id'      => $this->landlord->user_id,
            'business_name'    => 'Santos Rentals Cebu',
            'description'      => 'Rooms near the IT Park.',
            'contact_number'   => '09171110000',
            'business_address' => 'Apas, Cebu City',
        ]);

        $this->property = $this->makeProperty($this->landlord);
        $this->makeUnit($this->property);
    }

    private function review(string $first = 'Juan', string $last = 'Dela Cruz'): Review
    {
        $tenant = User::factory()->create(['first_name' => $first, 'last_name' => $last]);
        $tenant->assignRole('Tenant');

        return Review::create([
            'tenant_id'      => $tenant->user_id,
            'property_id'    => $this->property->property_id,
            'landlord_id'    => $this->landlord->user_id,
            'rating'         => 5,
            'review_comment' => 'Clean rooms and a fair landlord.',
            'is_hidden'      => false,
        ]);
    }

    // ─── names ──────────────────────────────────────────────────

    public function test_the_public_name_is_the_business_with_a_safe_fallback(): void
    {
        $this->assertSame('Santos Rentals Cebu', $this->landlord->fresh()->publicName());
        $this->assertSame('SR', $this->landlord->fresh()->publicInitials());

        $this->landlord->rentalBusiness()->update(['business_name' => null]);

        $this->assertSame('Maria S.', $this->landlord->fresh()->publicName());
        $this->assertSame('MS', $this->landlord->fresh()->publicInitials());
    }

    public function test_a_tenant_is_shortened_to_first_name_and_last_initial(): void
    {
        $this->assertSame('Juan D.', User::factory()->make(['first_name' => 'Juan', 'last_name' => 'Dela Cruz'])->shortName());
        $this->assertSame('Ana', User::factory()->make(['first_name' => 'Ana', 'last_name' => ''])->shortName());
    }

    // ─── listing page ───────────────────────────────────────────

    public function test_a_guest_sees_the_business_on_the_listing_and_nothing_personal(): void
    {
        $this->review();

        $this->get(route('properties.show', $this->property))
            ->assertOk()
            ->assertSee('Santos Rentals Cebu')
            ->assertSee('View business')
            ->assertSee('Juan D.')
            ->assertDontSee('Maria Santos')
            ->assertDontSee('maria.private@example.test')
            ->assertDontSee('09179998888')
            ->assertDontSee('Dela Cruz')
            // The business number sits behind the login even though the button is visible.
            ->assertDontSee('09171110000');
    }

    public function test_a_logged_in_tenant_can_reveal_the_business_number_but_never_the_personal_one(): void
    {
        $tenant = $this->makeTenant();

        $this->actingAs($tenant)->get(route('properties.show', $this->property))
            ->assertOk()
            ->assertSee('Show phone number')
            ->assertSee('09171110000')
            ->assertDontSee('09179998888');
    }

    public function test_no_business_number_means_no_reveal_button(): void
    {
        $this->landlord->rentalBusiness()->update(['contact_number' => null]);

        $this->actingAs($this->makeTenant())->get(route('properties.show', $this->property))
            ->assertOk()
            ->assertDontSee('Show phone number')
            ->assertDontSee('09179998888');
    }

    public function test_the_listing_view_never_prints_the_landlords_personal_details_again(): void
    {
        $view = file_get_contents(resource_path('views/properties/show.blade.php'));

        foreach (['landlord->first_name', 'landlord->last_name', 'landlord->email', 'landlord->contact_number', 'landlord->profile_picture'] as $needle) {
            $this->assertStringNotContainsString($needle, $view, "properties/show.blade.php reads {$needle}; use User::publicName() and the business instead.");
        }
    }

    // ─── business page ──────────────────────────────────────────

    public function test_a_visitor_sees_a_business_page_with_no_personal_details(): void
    {
        $this->review();

        $this->actingAs($this->makeTenant())->get(route('landlord.profile.show', $this->landlord))
            ->assertOk()
            ->assertSee('Santos Rentals Cebu')
            ->assertSee('Rooms near the IT Park.')
            ->assertSee('Juan D.')
            ->assertDontSee('maria.private@example.test')
            ->assertDontSee('09179998888')
            ->assertDontSee('Maria')
            ->assertDontSee('Email not verified')
            ->assertDontSee('Dela Cruz');
    }

    public function test_the_business_page_needs_a_login_and_the_number_needs_a_tap(): void
    {
        $this->get(route('landlord.profile.show', $this->landlord))->assertRedirect(route('login'));

        $this->actingAs($this->makeTenant())->get(route('landlord.profile.show', $this->landlord))
            ->assertOk()
            ->assertSee('Show phone number')
            ->assertSee('09171110000');
    }

    public function test_the_owner_still_sees_their_personal_details_marked_private(): void
    {
        $this->actingAs($this->landlord)->get(route('landlord.profile.me'))
            ->assertOk()
            ->assertSee('maria.private@example.test')
            ->assertSee('09179998888')
            ->assertSee('Only you can see this');
    }

    public function test_a_private_landlord_with_a_live_listing_still_has_a_business_page(): void
    {
        $this->landlord->update(['profile_visibility' => 'private']);

        $tenant = $this->makeTenant();

        $this->actingAs($tenant)->get(route('landlord.profile.show', $this->landlord))->assertOk()->assertSee('Santos Rentals Cebu');
        $this->actingAs($tenant)->get(route('properties.show', $this->property))->assertOk()->assertSee('View business');
    }

    public function test_a_private_landlord_with_nothing_live_stays_hidden(): void
    {
        $this->landlord->update(['profile_visibility' => 'private']);
        $this->property->update(['publication_status' => 'Unpublished']);

        $this->actingAs($this->makeTenant())->get(route('landlord.profile.show', $this->landlord))->assertNotFound();
        $this->assertFalse($this->landlord->fresh()->hasPublicBusinessPage());
    }

    public function test_a_public_landlord_with_nothing_live_follows_their_visibility_setting(): void
    {
        $this->property->update(['publication_status' => 'Unpublished']);

        $this->actingAs($this->makeTenant())->get(route('landlord.profile.show', $this->landlord))->assertOk();
    }

    // ─── public API ─────────────────────────────────────────────

    public function test_the_public_property_api_sends_the_business_not_the_person(): void
    {
        $this->review();

        $json = $this->getJson("/api/v1/properties/{$this->property->property_id}")->assertOk()->json('data');

        $this->assertSame('Santos Rentals Cebu', $json['landlord']['business_name']);
        foreach (['email', 'contact_number', 'first_name', 'last_name', 'profile_picture', 'business_contact_number'] as $key) {
            $this->assertArrayNotHasKey($key, $json['landlord'], "landlord.{$key} must not be public");
        }

        $this->assertSame('Juan D.', $json['reviews'][0]['tenant']['short_name']);
        $this->assertArrayNotHasKey('last_name', $json['reviews'][0]['tenant']);
        $this->assertStringNotContainsString('maria.private', json_encode($json));
        $this->assertStringNotContainsString('09179998888', json_encode($json));
    }

    public function test_the_business_number_in_the_api_needs_a_token(): void
    {
        $tenant = $this->makeTenant();

        $guest = $this->getJson("/api/v1/properties/{$this->property->property_id}")->json('data.landlord');
        $this->assertArrayNotHasKey('business_contact_number', $guest);

        $authed = $this->actingAs($tenant, 'sanctum')->getJson("/api/v1/properties/{$this->property->property_id}")->json('data.landlord');
        $this->assertSame('09171110000', $authed['business_contact_number']);
    }

    public function test_the_browse_api_nests_the_business_too(): void
    {
        $row = collect($this->getJson('/api/v1/properties?per_page=50')->assertOk()->json('data'))
            ->firstWhere('property_id', $this->property->property_id);

        $this->assertSame('Santos Rentals Cebu', $row['landlord']['business_name']);
        $this->assertArrayNotHasKey('last_name', $row['landlord']);
    }

    public function test_the_landlord_profile_api_hides_personal_details_from_visitors(): void
    {
        $this->review();

        $guest = $this->getJson("/api/v1/landlord/{$this->landlord->user_id}/profile")->assertOk()->json('data');

        $this->assertSame('Santos Rentals Cebu', $guest['user']['business_name']);
        $this->assertArrayNotHasKey('first_name', $guest['user']);
        $this->assertArrayNotHasKey('contact_number', $guest['business']);
        $this->assertSame('Juan D.', $guest['reviews'][0]['tenant']['short_name']);

        $viewer = $this->actingAs($this->makeTenant(), 'sanctum')->getJson("/api/v1/landlord/{$this->landlord->user_id}/profile")->json('data');
        $this->assertSame('09171110000', $viewer['business']['contact_number']);

        $owner = $this->actingAs($this->landlord, 'sanctum')->getJson("/api/v1/landlord/{$this->landlord->user_id}/profile")->json('data');
        $this->assertSame('Maria', $owner['user']['first_name']);
    }
}
