<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\TestCase;

/**
 * A landlord edits their business page (name, description, contact, address, logo) from a sheet on My Profile,
 * the page tenants see, instead of on Settings.
 */
class LandlordBusinessSheetTest extends TestCase
{
    use CreatesMarketplaceFixtures, DatabaseTransactions;

    public function test_my_profile_carries_the_sheet_and_the_owner_strip(): void
    {
        $html = $this->actingAs($this->makeLandlord())->get(route('landlord.profile.me'))->assertOk()->getContent();

        $this->assertStringContainsString("\$dispatch('open-business-sheet')", $html);
        $this->assertStringContainsString('Edit business page', $html);
        $this->assertStringContainsString('name="business_name"', $html);
        $this->assertStringContainsString('name="return_to" value="profile"', $html);
        $this->assertStringContainsString('This is what tenants see.', $html);
        $this->assertStringContainsString(route('profile.edit').'#visibility', $html);
    }

    public function test_a_visitor_gets_neither_the_sheet_nor_the_strip(): void
    {
        $landlord = $this->makeLandlord();
        $this->makeProperty($landlord); // a live listing keeps the page public

        $this->actingAs($this->makeTenant())->get(route('landlord.profile.show', $landlord))
            ->assertOk()
            ->assertDontSee('open-business-sheet', false)
            ->assertDontSee('This is what tenants see.');
    }

    public function test_saving_the_sheet_lands_back_on_my_profile_and_leaves_gcash_alone(): void
    {
        $landlord = $this->makeLandlord();
        $landlord->savePayoutAccount('09171112222', 'Maria Santos');

        $this->actingAs($landlord)->patch(route('landlord.profile.update'), [
            'return_to' => 'profile', 'business_name' => 'Santos Rentals',
            'business_description' => 'Rooms near USC.', 'business_address' => 'Cebu City',
        ])->assertRedirect(route('landlord.profile.me'))->assertSessionHas('success', 'Business page saved.');

        $landlord = $landlord->fresh();
        $this->assertSame('Santos Rentals', $landlord->rentalBusiness->business_name);
        $this->assertSame('Rooms near USC.', $landlord->rentalBusiness->description);
        $this->assertSame('09171112222', $landlord->payoutAccount->gcash_number);
    }

    public function test_an_invalid_field_goes_back_with_the_error_so_the_sheet_reopens(): void
    {
        $landlord = $this->makeLandlord();

        $this->actingAs($landlord)->from(route('landlord.profile.me'))->patch(route('landlord.profile.update'), [
            'return_to' => 'profile', 'business_name' => str_repeat('a', 300),
        ])->assertRedirect(route('landlord.profile.me'))->assertSessionHasErrors('business_name');

        // The rendered page opens the sheet straight away when it carries a business error.
        $this->withSession(['errors' => (new ViewErrorBag)->put('default', new MessageBag(['business_name' => 'Too long']))])
            ->get(route('landlord.profile.me'))
            ->assertSee('open: true', false)
            ->assertSee('Too long');
    }
}
