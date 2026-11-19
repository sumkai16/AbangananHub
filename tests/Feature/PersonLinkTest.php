<?php

namespace Tests\Feature;

use App\Models\LandlordTenant;
use App\Support\ProfileLinks;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\Support\CreatesPaymentFixtures;
use Tests\TestCase;

/** A name that leads to a profile: who gets a link, where it goes, and who must not open it. */
class PersonLinkTest extends TestCase
{
    use CreatesMarketplaceFixtures, CreatesPaymentFixtures, DatabaseTransactions;

    public function test_a_landlord_gets_a_link_to_their_own_tenant_and_nobody_else(): void
    {
        ['landlord' => $landlord, 'tenant' => $tenant] = $this->makeTenancy();
        $row = LandlordTenant::where('landlord_id', $landlord->user_id)->where('tenant_id', $tenant->user_id)->firstOrFail();

        $this->assertSame(route('landlord.tenants.profile', $row), ProfileLinks::url($tenant, $landlord));
        $this->assertNull(ProfileLinks::url($this->makeTenant(), $landlord), 'a tenant of nobody they know has no page');
        $this->assertNull(ProfileLinks::url($this->makeLandlord(), $landlord), 'a landlord never opens another landlord from a name');
        $this->assertNull(ProfileLinks::url($landlord, $landlord));
        $this->assertNull(ProfileLinks::url(null, $landlord));
    }

    public function test_tenants_never_get_a_link_to_another_tenant(): void
    {
        ['tenant' => $tenant] = $this->makeTenancy();

        $this->assertNull(ProfileLinks::url($this->makeTenant(), $tenant));
    }

    public function test_a_tenant_links_a_landlord_with_an_open_page_and_not_one_whose_page_is_private(): void
    {
        ['landlord' => $live, 'tenant' => $tenant] = $this->makeTenancy();
        $this->assertSame(route('landlord.profile.show', $live->user_id), ProfileLinks::url($live, $tenant), 'a live listing keeps the page open');

        // A landlord with nothing live: the page follows their visibility setting, so a private one would be a 404.
        $quiet = $this->makeLandlord();
        $quiet->forceFill(['profile_visibility' => 'private'])->save();
        $this->assertNull(ProfileLinks::url($quiet->fresh(), $tenant));

        $quiet->forceFill(['profile_visibility' => 'public'])->save();
        request()->attributes->replace([]); // the answer is read once per request
        $this->assertSame(route('landlord.profile.show', $quiet->user_id), ProfileLinks::url($quiet->fresh(), $tenant));
    }

    public function test_the_tenants_page_links_each_name_in_a_new_tab(): void
    {
        ['landlord' => $landlord, 'tenant' => $tenant] = $this->makeTenancy();
        $row = LandlordTenant::where('landlord_id', $landlord->user_id)->firstOrFail();

        $this->actingAs($landlord)->get(route('landlord.tenants.index'))->assertOk()
            ->assertSee('href="'.route('landlord.tenants.profile', $row).'" target="_blank" rel="noopener"', false)
            ->assertSee('opens profile in a new tab');
    }

    public function test_the_tenant_page_shows_their_details_to_their_landlord_only(): void
    {
        ['landlord' => $landlord, 'tenant' => $tenant] = $this->makeTenancy();
        $row = LandlordTenant::where('landlord_id', $landlord->user_id)->firstOrFail();

        $this->actingAs($landlord)->get(route('landlord.tenants.profile', $row))->assertOk()
            ->assertSee($tenant->first_name)->assertSee('Emergency contact and ID')->assertSee('Rentals with you');

        $this->actingAs($this->makeLandlord())->get(route('landlord.tenants.profile', $row))->assertForbidden();
        $this->actingAs($tenant)->get(route('landlord.tenants.profile', $row))->assertForbidden();
    }

    public function test_the_tenant_rental_page_links_the_landlord_when_their_page_is_open(): void
    {
        ['landlord' => $landlord, 'tenant' => $tenant, 'reservation' => $reservation] = $this->makeTenancy();

        $this->actingAs($tenant)->get(route('tenancy.show', $reservation))->assertOk()
            ->assertSee('href="'.route('landlord.profile.show', $landlord->user_id).'" target="_blank"', false);
    }

    public function test_the_chat_links_the_other_person_in_the_header_and_for_live_messages(): void
    {
        ['landlord' => $landlord, 'tenant' => $tenant, 'reservation' => $reservation] = $this->makeTenancy();
        $thread = $reservation->openTenancyThread();
        $row = LandlordTenant::where('landlord_id', $landlord->user_id)->firstOrFail();
        $profile = route('landlord.tenants.profile', $row);

        $page = $this->actingAs($landlord)->get(route('conversations.show', $thread), ['X-Requested-With' => 'XMLHttpRequest'])->assertOk();
        $page->assertSee('href="'.$profile.'" target="_blank"', false)
            // The script that draws a message the moment it arrives reads the address from here.
            ->assertSee('data-other-profile="'.$profile.'"', false);
    }

    public function test_the_chat_draws_no_link_for_someone_the_landlord_has_no_page_for(): void
    {
        ['landlord' => $landlord, 'reservation' => $reservation] = $this->makeTenancy();
        $thread = $reservation->openTenancyThread();
        $prospect = $this->makeTenant();
        $thread->forceFill(['tenant_id' => $prospect->user_id])->save();

        $this->actingAs($landlord)->get(route('conversations.show', $thread->fresh()), ['X-Requested-With' => 'XMLHttpRequest'])->assertOk()
            ->assertSee('data-other-profile=""', false);
    }
}
