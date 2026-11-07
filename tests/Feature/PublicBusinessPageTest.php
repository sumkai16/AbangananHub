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
 * The business page and its public API show only live listings and reviews an admin has not hidden.
 */
class PublicBusinessPageTest extends TestCase
{
    use CreatesMarketplaceFixtures, DatabaseTransactions;

    private User $landlord;

    private Property $live;

    protected function setUp(): void
    {
        parent::setUp();

        config(['broadcasting.default' => 'null']);

        $this->landlord = User::factory()->create(['profile_visibility' => 'public']);
        $this->landlord->assignRole('Landlord');
        RentalBusiness::create(['landlord_id' => $this->landlord->user_id, 'business_name' => 'Visible Rentals Co']);

        $this->live = $this->makeProperty($this->landlord, ['title' => 'Sunny Live Apartment']);
        $this->makeUnit($this->live);
    }

    private function review(Property $property, string $comment, int $rating, bool $hidden): Review
    {
        $tenant = $this->makeTenant();

        return Review::create([
            'tenant_id'      => $tenant->user_id,
            'property_id'    => $property->property_id,
            'landlord_id'    => $this->landlord->user_id,
            'rating'         => $rating,
            'review_comment' => $comment,
            'is_hidden'      => $hidden,
        ]);
    }

    public function test_a_hidden_review_is_not_on_the_web_page(): void
    {
        $this->review($this->live, 'Lovely quiet place indeed', 5, false);
        $this->review($this->live, 'Moderated rant zzqx', 1, true);

        $this->actingAs($this->makeTenant())->get(route('landlord.profile.show', $this->landlord))
            ->assertOk()
            ->assertSee('Lovely quiet place indeed')
            ->assertDontSee('Moderated rant zzqx');
    }

    public function test_a_hidden_review_is_not_in_the_api_list_or_average(): void
    {
        $this->review($this->live, 'Lovely quiet place indeed', 5, false);
        $this->review($this->live, 'Moderated rant zzqx', 1, true);

        $data = $this->getJson("/api/v1/landlord/{$this->landlord->user_id}/profile")->assertOk()->json('data');

        $this->assertCount(1, $data['reviews']);
        $this->assertStringNotContainsString('Moderated rant zzqx', json_encode($data));
        $this->assertEquals(5.0, $data['average_rating']);
    }

    public function test_an_unpublished_or_unapproved_property_is_not_listed_on_the_web_page(): void
    {
        $this->makeProperty($this->landlord, ['title' => 'Quietly Unpublished Villa', 'publication_status' => 'Unpublished']);
        $this->makeProperty($this->landlord, ['title' => 'Still Pending Bungalow', 'verification_status' => 'Pending']);

        $this->actingAs($this->makeTenant())->get(route('landlord.profile.show', $this->landlord))
            ->assertOk()
            ->assertSee('Sunny Live Apartment')
            ->assertDontSee('Quietly Unpublished Villa')
            ->assertDontSee('Still Pending Bungalow');
    }

    public function test_an_unpublished_or_unapproved_property_is_not_in_the_api(): void
    {
        $this->makeProperty($this->landlord, ['title' => 'Quietly Unpublished Villa', 'publication_status' => 'Unpublished']);
        $this->makeProperty($this->landlord, ['title' => 'Still Pending Bungalow', 'verification_status' => 'Pending']);

        $titles = collect($this->getJson("/api/v1/landlord/{$this->landlord->user_id}/profile")->assertOk()->json('data.properties'))
            ->pluck('title')->all();

        $this->assertSame(['Sunny Live Apartment'], $titles);
    }

    public function test_reviews_on_an_unpublished_property_are_not_shown_either(): void
    {
        $hiddenListing = $this->makeProperty($this->landlord, ['title' => 'Quietly Unpublished Villa', 'publication_status' => 'Unpublished']);
        $this->review($hiddenListing, 'Review on an unlisted place', 4, false);

        $this->actingAs($this->makeTenant())->get(route('landlord.profile.show', $this->landlord))
            ->assertOk()
            ->assertDontSee('Review on an unlisted place');
    }
}
