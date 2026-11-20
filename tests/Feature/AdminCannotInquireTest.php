<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Reservation;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\TestCase;

/** An administrator can look at a listing as a tenant would, but cannot open a chat or a reservation on it. */
class AdminCannotInquireTest extends TestCase
{
    use CreatesMarketplaceFixtures, DatabaseTransactions;

    public function test_an_admin_cannot_start_a_conversation(): void
    {
        $admin = $this->makeUserWithRole('Admin');
        $property = $this->makeProperty($this->makeLandlord());

        $this->actingAs($admin)->post(route('conversations.store'), ['property_id' => $property->property_id])
            ->assertForbidden();

        $this->assertSame(0, Conversation::where('tenant_id', $admin->user_id)->count());
    }

    public function test_an_admin_who_also_holds_the_tenant_role_cannot_reserve(): void
    {
        $admin = $this->makeUserWithRole('Admin');
        $admin->assignRole('Tenant');
        $unit = $this->makeUnit($this->makeProperty($this->makeLandlord()));

        $this->actingAs($admin)->post(route('reservations.store'), ['unit_id' => $unit->unit_id])->assertForbidden();

        $this->assertSame(0, Reservation::where('tenant_id', $admin->user_id)->count());
    }

    public function test_the_mobile_api_refuses_an_admin_too(): void
    {
        $admin = $this->makeUserWithRole('Admin');
        $property = $this->makeProperty($this->makeLandlord());
        Sanctum::actingAs($admin);

        $this->postJson('/api/v1/conversations', ['property_id' => $property->property_id])->assertForbidden();
    }

    public function test_a_tenant_can_still_start_a_conversation(): void
    {
        $tenant = $this->makeTenant();
        $property = $this->makeProperty($this->makeLandlord());

        $this->actingAs($tenant)->post(route('conversations.store'), ['property_id' => $property->property_id])
            ->assertRedirect();

        $this->assertSame(1, Conversation::where('tenant_id', $tenant->user_id)->count());
    }

    public function test_the_listing_page_shows_an_admin_no_send_inquiry_button(): void
    {
        $admin = $this->makeUserWithRole('Admin');
        $property = $this->makeProperty($this->makeLandlord());
        $this->makeUnit($property);

        $this->actingAs($admin)->get(route('properties.show', $property))
            ->assertOk()
            ->assertSee("Admins can't send inquiries", false)
            ->assertDontSee('x-on:click="inquireOpen = true"', false);
    }
}
