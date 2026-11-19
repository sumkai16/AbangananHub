<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Blade;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\TestCase;

class EmptyStateComponentTest extends TestCase
{
    use CreatesMarketplaceFixtures, DatabaseTransactions;

    public function test_the_original_standalone_look_is_unchanged_for_older_pages(): void
    {
        $html = Blade::render('<x-empty-state title="No tenants yet" message="Add one." href="/x" cta="Add a tenant" />');

        $this->assertStringContainsString('border-dashed', $html);
        $this->assertStringContainsString('No tenants yet', $html);
        $this->assertStringContainsString('Add one.', $html);
        $this->assertStringContainsString('Add a tenant', $html);
    }

    public function test_the_flat_version_sits_in_a_card_and_fills_the_free_height(): void
    {
        $html = Blade::render('<x-empty-state flat fill symbol="wallet" title="No payments here" note="Wait for one."><a href="/all">See all</a></x-empty-state>');

        $this->assertStringNotContainsString('border-dashed', $html);
        $this->assertStringContainsString('flex-1', $html);
        $this->assertStringContainsString('<svg', $html);
        $this->assertStringContainsString('Wait for one.', $html);
        $this->assertStringContainsString('See all', $html);
    }

    public function test_compact_has_no_icon_and_an_unknown_symbol_falls_back(): void
    {
        $this->assertStringNotContainsString('<svg', Blade::render('<x-empty-state flat compact title="None" />'));
        $this->assertStringContainsString('<svg', Blade::render('<x-empty-state flat symbol="nope" title="None" />'));
    }

    public function test_empty_admin_money_pages_explain_themselves_and_offer_the_full_list(): void
    {
        $admin = $this->makeUserWithRole('Admin');

        $this->actingAs($admin)->get(route('admin.payments.index', ['status' => 'Held']))
            ->assertOk()->assertSee('Nothing is being held')->assertSee('See all payments');
        $this->actingAs($admin)->get(route('admin.deposit-charges.index'))
            ->assertOk()->assertSee('No active deposit charges')->assertSee('See all charges');
        $this->actingAs($admin)->get(route('admin.payments.index', ['status' => 'All']))
            ->assertOk()->assertSee('No payments yet')->assertDontSee('See all payments');
    }
}
