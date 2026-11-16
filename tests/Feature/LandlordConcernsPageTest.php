<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\TenantConcern;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\Support\CreatesPaymentFixtures;
use Tests\TestCase;

/** The landlord's Concerns page: every concern their tenants reported, filterable, and only theirs. */
class LandlordConcernsPageTest extends TestCase
{
    use CreatesMarketplaceFixtures, CreatesPaymentFixtures, DatabaseTransactions;

    private function concern($reservation, $tenant, string $description, string $status = 'Open'): TenantConcern
    {
        $thread = Conversation::tenancies()->where('reservation_id', $reservation->reservation_id)->firstOrFail();

        return TenantConcern::create([
            'conversation_id' => $thread->conversation_id, 'reservation_id' => $reservation->reservation_id, 'tenant_id' => $tenant->user_id,
            'category' => 'Plumbing', 'description' => $description, 'status' => $status,
        ]);
    }

    public function test_it_lists_the_landlords_concerns_and_needs_attention_is_the_default(): void
    {
        ['tenant' => $tenant, 'landlord' => $landlord, 'reservation' => $reservation] = $this->makeTenancy();
        $this->concern($reservation, $tenant, 'Faucet keeps dripping');
        $this->concern($reservation, $tenant, 'Light was replaced', 'Fixed');

        $this->actingAs($landlord)->get(route('landlord.concerns.index'))
            ->assertOk()->assertSee('Faucet keeps dripping')->assertDontSee('Light was replaced');

        $this->actingAs($landlord)->get(route('landlord.concerns.index', ['status' => 'all']))
            ->assertOk()->assertSee('Faucet keeps dripping')->assertSee('Light was replaced');
    }

    public function test_another_landlords_concerns_are_never_shown(): void
    {
        ['tenant' => $tenant, 'reservation' => $reservation] = $this->makeTenancy();
        $this->concern($reservation, $tenant, 'Private leak report');

        $this->actingAs($this->makeLandlord())->get(route('landlord.concerns.index', ['status' => 'all']))
            ->assertOk()->assertDontSee('Private leak report')->assertSee('No concerns yet');
    }

    public function test_the_sidebar_badge_counts_unresolved_concerns(): void
    {
        ['tenant' => $tenant, 'landlord' => $landlord, 'reservation' => $reservation] = $this->makeTenancy();
        $this->concern($reservation, $tenant, 'One');
        $this->concern($reservation, $tenant, 'Two', 'In Progress');
        $this->concern($reservation, $tenant, 'Three', 'Fixed');

        $this->actingAs($landlord)->get(route('landlord.concerns.index'))->assertOk()->assertSee('Concerns')->assertSee('2 waiting');
    }

    public function test_a_landlord_can_start_and_finish_a_concern_through_the_existing_route(): void
    {
        ['tenant' => $tenant, 'landlord' => $landlord, 'reservation' => $reservation] = $this->makeTenancy();
        $concern = $this->concern($reservation, $tenant, 'Outlet is dead');

        $this->actingAs($landlord)->patchJson(route('concerns.status', $concern), ['status' => 'In Progress'])->assertOk();
        $this->actingAs($landlord)->patchJson(route('concerns.status', $concern), ['status' => 'Fixed'])->assertOk();
        $this->assertSame('Fixed', $concern->fresh()->status);
    }
}
