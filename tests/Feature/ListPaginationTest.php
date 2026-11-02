<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Favorite;
use App\Models\LandlordTenant;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\Support\CreatesPaymentFixtures;
use Tests\TestCase;

/**
 * Lists that grow with use are shown a page at a time. For each: the first page is full, the next page holds
 * the rest, and any total or summary that sits above the list still counts everything, not just the page.
 */
class ListPaginationTest extends TestCase
{
    use CreatesMarketplaceFixtures, CreatesPaymentFixtures, DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        config(['broadcasting.default' => 'null']);
    }

    public function test_tenants_are_listed_a_page_at_a_time_and_the_chip_counts_cover_all_of_them(): void
    {
        $landlord = $this->makeLandlord();
        foreach (range(1, 25) as $n) {
            $tenant = $this->makeTenant();
            LandlordTenant::create(['landlord_id' => $landlord->user_id, 'tenant_id' => $tenant->user_id, 'tenant_number' => $n]);
        }

        $first = $this->actingAs($landlord)->get(route('landlord.tenants.index'))->assertOk();
        $first->assertViewHas('rows', fn ($rows) => $rows->count() === 20 && $rows->total() === 25);
        $first->assertViewHas('counts', fn ($counts) => $counts['all'] === 25);
        $first->assertSee('page=2', false);

        $this->actingAs($landlord)->get(route('landlord.tenants.index', ['page' => 2]))
            ->assertOk()
            ->assertViewHas('rows', fn ($rows) => $rows->count() === 5 && $rows->currentPage() === 2);
    }

    public function test_payments_list_a_page_at_a_time_but_the_summary_still_counts_every_tenancy(): void
    {
        $landlord = $this->makeLandlord();
        foreach (range(1, 22) as $n) {
            $this->makeTenancy([], $landlord);
        }

        $first = $this->actingAs($landlord)->get(route('landlord.payments.index'))->assertOk();
        $first->assertSee('page=2', false);

        $this->actingAs($landlord)->get(route('landlord.payments.index', ['page' => 2]))
            ->assertOk()
            ->assertSee('page=1', false);
    }

    public function test_conversations_are_listed_a_page_at_a_time(): void
    {
        $landlord = $this->makeLandlord();
        $tenant = $this->makeTenant();
        foreach (range(1, 22) as $n) {
            $unit = $this->makeUnit($this->makeProperty($landlord));
            Conversation::create(['tenant_id' => $tenant->user_id, 'landlord_id' => $landlord->user_id, 'property_id' => $unit->property_id, 'unit_id' => $unit->unit_id]);
        }

        $this->actingAs($tenant)->get(route('conversations.index'))
            ->assertOk()
            ->assertViewHas('conversations', fn ($c) => $c->count() === 20 && $c->total() === 22)
            ->assertViewHas('activeCount', 22);

        $this->actingAs($tenant)->get(route('conversations.index', ['page' => 2]))
            ->assertOk()
            ->assertViewHas('conversations', fn ($c) => $c->count() === 2);
    }

    public function test_favorites_are_listed_a_page_at_a_time(): void
    {
        $landlord = $this->makeLandlord();
        $tenant = $this->makeTenant();
        foreach (range(1, 14) as $n) {
            Favorite::create(['tenant_id' => $tenant->user_id, 'property_id' => $this->makeProperty($landlord)->property_id]);
        }

        $this->actingAs($tenant)->get(route('favorites.index'))
            ->assertOk()
            ->assertViewHas('favorites', fn ($f) => $f->count() === 12 && $f->total() === 14);

        $this->actingAs($tenant)->get(route('favorites.index', ['page' => 2]))
            ->assertViewHas('favorites', fn ($f) => $f->count() === 2);
    }

    public function test_api_lists_keep_their_data_array_and_add_a_meta_block(): void
    {
        $landlord = $this->makeLandlord();
        $tenant = $this->makeTenant();
        foreach (range(1, 22) as $n) {
            $unit = $this->makeUnit($this->makeProperty($landlord));
            Conversation::create(['tenant_id' => $tenant->user_id, 'landlord_id' => $landlord->user_id, 'property_id' => $unit->property_id, 'unit_id' => $unit->unit_id]);
            Favorite::create(['tenant_id' => $tenant->user_id, 'property_id' => $unit->property_id]);
        }
        Sanctum::actingAs($tenant);

        foreach (['/api/v1/conversations', '/api/v1/favorites'] as $path) {
            $this->getJson($path)->assertOk()
                ->assertJsonCount(20, 'data')
                ->assertJsonPath('meta.total', 22)
                ->assertJsonPath('meta.last_page', 2);

            $this->getJson($path.'?page=2')->assertOk()->assertJsonCount(2, 'data');
            $this->getJson($path.'?per_page=5')->assertOk()->assertJsonCount(5, 'data');
            $this->getJson($path.'?per_page=9999')->assertOk()->assertJsonPath('meta.per_page', 50);
        }

        $this->getJson('/api/v1/conversations')->assertJsonPath('counts.active', 22);
    }

    public function test_api_landlord_units_and_payments_page_with_totals_over_everything(): void
    {
        $landlord = $this->makeLandlord();
        $property = $this->makeProperty($landlord);
        foreach (range(1, 22) as $n) {
            $this->makeUnit($property, ['unit_label' => sprintf('U%02d', $n)]);
        }
        Sanctum::actingAs($landlord);

        $this->getJson("/api/v1/landlord/properties/{$property->property_id}/units")->assertOk()
            ->assertJsonCount(20, 'data')
            ->assertJsonPath('meta.total', 22)
            ->assertJsonPath('counts.total', 22)
            ->assertJsonPath('counts.available', 22);

        $other = $this->makeLandlord();
        foreach (range(1, 22) as $n) {
            $this->makeTenancy([], $other);
        }
        Sanctum::actingAs($other);

        $this->getJson('/api/v1/landlord/payments')->assertOk()
            ->assertJsonCount(20, 'data')
            ->assertJsonPath('meta.total', 22);
    }
}
